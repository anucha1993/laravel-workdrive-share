<?php

namespace App\Services\WorkDrive;

use App\Mail\ShareSummaryMail;
use App\Models\ShareGrant;
use App\Models\ShareGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * แชร์สิทธิ์ "ดูอย่างเดียว" บนไฟล์/โฟลเดอร์ต้นฉบับโดยตรง
 * สิทธิ์ที่ควรมี = ทุกรายการที่ pick × ทุกอีเมลในกลุ่ม แล้วเทียบกับ share_grants:
 *   ยังไม่มี → POST /permissions | ไม่ต้องมีแล้ว → DELETE /permissions/{id}
 * ระบบไม่แก้ ย้าย หรือลบต้นฉบับ — แตะแค่สิทธิ์แชร์ที่ระบบเป็นผู้ให้เท่านั้น
 */
class SharePermissionService
{
    public function __construct(private WorkDriveClient $wd)
    {
    }

    /** @return array{granted:int,revoked:int,errors:int,newly_granted:array<int,string>,skipped?:string} */
    public function apply(ShareGroup $group, string $trigger = 'manual'): array
    {
        $lock = Cache::lock("workdrive-share-{$group->id}", 3600);
        if (!$lock->get()) {
            return ['granted' => 0, 'revoked' => 0, 'errors' => 0, 'newly_granted' => [], 'skipped' => 'กำลังทำงานอยู่'];
        }

        $run = $group->runs()->create(['trigger' => $trigger, 'status' => 'running', 'started_at' => now()]);
        $stats = ['granted' => 0, 'revoked' => 0, 'errors' => 0];
        $newly = [];
        $log = [];

        try {
            // 1) อ่านข้อมูลต้นฉบับ (ชื่อ ชนิด ลิงก์) — รายการที่เปิดไม่ได้จะไม่ถูกแชร์ แต่สิทธิ์เดิมยังคงอยู่
            $isFolder = [];
            foreach ($group->picks()->get()->groupBy('source_id') as $sourceId => $picks) {
                try {
                    $item = $this->wd->info($sourceId);
                    $a = $item['attributes'] ?? [];
                    $isFolder[$sourceId] = WorkDriveClient::isFolder($item);
                    foreach ($picks as $pick) {
                        $pick->update([
                            'source_name' => $a['name'] ?? $sourceId,
                            'source_type' => $isFolder[$sourceId] ? 'folder' : 'file',
                            'permalink' => $a['permalink'] ?? null,
                            'status' => 'ok',
                            'error' => null,
                        ]);
                    }
                } catch (Throwable $e) {
                    $stats['errors']++;
                    $log[] = "เปิดต้นฉบับไม่ได้ {$sourceId}: " . mb_substr($e->getMessage(), 0, 300);
                    foreach ($picks as $pick) {
                        $pick->update(['status' => 'error', 'error' => mb_substr($e->getMessage(), 0, 500)]);
                    }
                }
            }

            $sourceIds = $group->picks()->distinct()->pluck('source_id');
            $emails = $group->recipients()->pluck('email');
            $existing = $group->grants()->get()->keyBy(fn ($g) => "{$g->resource_id}|{$g->email}");

            // 2) ให้สิทธิ์ที่ยังขาด
            foreach ($sourceIds as $sourceId) {
                if (!array_key_exists($sourceId, $isFolder)) {
                    continue; // เปิดต้นฉบับไม่ได้ในรอบนี้
                }
                foreach ($emails as $email) {
                    $grant = $existing->get("{$sourceId}|{$email}");
                    if ($grant?->status === 'granted') {
                        continue;
                    }

                    try {
                        $permissionId = $this->sharedByOtherGroup($group, $sourceId, $email)?->permission_id
                            ?? $this->wd->grantView($sourceId, $email, $isFolder[$sourceId]);

                        ShareGrant::updateOrCreate(
                            ['share_group_id' => $group->id, 'resource_id' => $sourceId, 'email' => $email],
                            ['permission_id' => $permissionId, 'status' => 'granted', 'error' => null, 'granted_at' => now()]
                        );
                        $stats['granted']++;
                        $newly[$email] = true;
                        $log[] = "ให้สิทธิ์ {$email} → {$sourceId}";
                    } catch (Throwable $e) {
                        ShareGrant::updateOrCreate(
                            ['share_group_id' => $group->id, 'resource_id' => $sourceId, 'email' => $email],
                            ['status' => 'error', 'error' => mb_substr($e->getMessage(), 0, 500)]
                        );
                        $stats['errors']++;
                        $log[] = "ให้สิทธิ์ไม่สำเร็จ {$email} → {$sourceId}: " . mb_substr($e->getMessage(), 0, 300);
                    }
                }
            }

            // 3) ถอนสิทธิ์ของรายการ/อีเมลที่ถูกเอาออก
            $desired = $sourceIds->crossJoin($emails)->map(fn ($p) => "{$p[0]}|{$p[1]}")->flip();
            foreach ($existing as $key => $grant) {
                if ($desired->has($key)) {
                    continue;
                }
                if ($grant->status !== 'granted' || !$grant->permission_id) {
                    $grant->delete(); // ไม่เคยให้สิทธิ์สำเร็จ ไม่มีอะไรต้องถอน
                    continue;
                }

                try {
                    // อีกกลุ่มยังใช้สิทธิ์เดียวกันอยู่ → ไม่ถอนใน WorkDrive แค่ลบแถวของกลุ่มนี้
                    if (!$this->sharedByOtherGroup($group, $grant->resource_id, $grant->email)) {
                        $this->revokeIgnoringMissing($grant->permission_id);
                    }
                    $grant->delete();
                    $stats['revoked']++;
                    $log[] = "ถอนสิทธิ์ {$grant->email} → {$grant->resource_id}";
                } catch (Throwable $e) {
                    $grant->update(['error' => mb_substr($e->getMessage(), 0, 500)]);
                    $stats['errors']++;
                    $log[] = "ถอนสิทธิ์ไม่สำเร็จ {$grant->email} → {$grant->resource_id}: " . mb_substr($e->getMessage(), 0, 300);
                }
            }

            $summary = "ให้สิทธิ์ {$stats['granted']} | ถอนสิทธิ์ {$stats['revoked']} | ผิดพลาด {$stats['errors']}";
            $group->update(['last_applied_at' => now(), 'last_result' => $summary]);
            $run->update($stats + ['status' => $stats['errors'] ? 'partial' : 'ok', 'finished_at' => now(), 'log' => implode(PHP_EOL, $log)]);

            return $stats + ['newly_granted' => array_keys($newly)];
        } catch (Throwable $e) {
            $log[] = 'ทำงานล้มเหลว: ' . $e->getMessage();
            $run->update($stats + ['status' => 'failed', 'finished_at' => now(), 'log' => implode(PHP_EOL, $log)]);
            throw $e;
        } finally {
            $lock->release();
        }
    }

    /** รายการที่อีเมลนี้เปิดได้แล้ว แยกตามหมวด (ใช้ทำอีเมลสรุป) */
    public function summaryFor(ShareGroup $group, string $email): Collection
    {
        $granted = $group->grants()->where('email', $email)->where('status', 'granted')->pluck('resource_id')->flip();

        return $group->picks()->get()
            ->filter(fn ($p) => $granted->has($p->source_id))
            ->groupBy('category');
    }

    /** ส่งอีเมลสรุปลิงก์ให้ผู้รับ (ข้ามคนที่ยังไม่มีสิทธิ์ใดๆ) คืนจำนวนที่ส่ง */
    public function sendSummaries(ShareGroup $group, ?array $emails = null): int
    {
        $sent = 0;
        foreach ($group->recipients()->get() as $recipient) {
            if ($emails !== null && !in_array($recipient->email, $emails, true)) {
                continue;
            }
            $items = $this->summaryFor($group, $recipient->email);
            if ($items->isEmpty()) {
                continue;
            }
            Mail::to($recipient->email, $recipient->name)->send(new ShareSummaryMail($group, $items));
            $recipient->update(['notified_at' => now()]);
            $sent++;
        }

        return $sent;
    }

    private function sharedByOtherGroup(ShareGroup $group, string $resourceId, string $email): ?ShareGrant
    {
        return ShareGrant::where('resource_id', $resourceId)->where('email', $email)
            ->where('status', 'granted')->where('share_group_id', '!=', $group->id)->first();
    }

    private function revokeIgnoringMissing(string $permissionId): void
    {
        try {
            $this->wd->revoke($permissionId);
        } catch (WorkDriveException $e) {
            // สิทธิ์ถูกถอนด้วยมือไปแล้ว ถือว่าถอนสำเร็จ
            if ($e->status !== 404 && $e->zohoCode !== 'R008') {
                throw $e;
            }
        }
    }
}
