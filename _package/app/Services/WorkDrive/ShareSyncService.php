<?php

namespace App\Services\WorkDrive;

use App\Models\ShareCopy;
use App\Models\ShareFolder;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ShareSyncService
{
    private array $pathCache = [];

    public function __construct(private WorkDriveClient $wd)
    {
    }

    /** สร้างโฟลเดอร์ของบริษัทใน WorkDrive (ถ้ายังไม่มี) */
    public function ensureFolder(ShareFolder $folder): string
    {
        if (!$folder->wd_folder_id) {
            $folder->wd_folder_id = $this->wd->findOrCreateFolder(config('workdrive.share_root_id'), $folder->name);
            $folder->save();
        }

        return $folder->wd_folder_id;
    }

    /**
     * ซิงก์ 1 โฟลเดอร์แชร์: คัดลอกไฟล์ใหม่ อัปเดตไฟล์ที่ต้นฉบับถูกแก้ และเอาไฟล์ที่ไม่ได้ pick แล้วออก
     * @return array{added:int,updated:int,removed:int,errors:int}
     */
    public function sync(ShareFolder $folder): array
    {
        $lock = Cache::lock("workdrive-sync-{$folder->id}", 3600);
        if (!$lock->get()) {
            return ['added' => 0, 'updated' => 0, 'removed' => 0, 'errors' => 0, 'skipped' => 'กำลังซิงก์อยู่'];
        }

        try {
            $this->pathCache = [];
            $root = $this->ensureFolder($folder);
            $stats = ['added' => 0, 'updated' => 0, 'removed' => 0, 'errors' => 0];

            // 1) รวบรวมไฟล์ที่ต้องมี  key = sourceId|path
            $desired = [];
            foreach ($folder->picks as $pick) {
                try {
                    $item = $this->wd->info($pick->source_id);
                    $name = $item['attributes']['name'] ?? $pick->source_id;

                    if (WorkDriveClient::isFolder($item)) {
                        foreach ($this->walk($pick->source_id, [$name]) as [$file, $rel]) {
                            $this->addDesired($desired, $file, $pick->category, $rel);
                        }
                    } else {
                        $this->addDesired($desired, $item, $pick->category, []);
                    }

                    $pick->update([
                        'source_name' => $name,
                        'source_type' => WorkDriveClient::isFolder($item) ? 'folder' : 'file',
                        'status' => 'ok',
                        'error' => null,
                    ]);
                } catch (Throwable $e) {
                    $pick->update(['status' => 'error', 'error' => mb_substr($e->getMessage(), 0, 500)]);
                    $stats['errors']++;
                }
            }

            // 2) เพิ่ม / อัปเดต
            $existing = $folder->copies()->get()->keyBy(fn ($c) => "{$c->source_file_id}|{$c->path}");

            foreach ($desired as $key => $d) {
                try {
                    $parent = $this->ensurePath($root, $d['dirs']);
                    $copy = $existing->get($key);

                    if ($copy && $copy->source_mtime === $d['mtime'] && $copy->dest_parent_id === $parent) {
                        continue;
                    }
                    if ($copy) {
                        $this->safeTrash($copy->dest_file_id);
                        $stats['updated']++;
                    } else {
                        $stats['added']++;
                    }

                    $newId = $this->wd->copy($d['source_id'], $parent);

                    ShareCopy::updateOrCreate(
                        ['share_folder_id' => $folder->id, 'source_file_id' => $d['source_id'], 'path' => $d['path']],
                        ['name' => $d['name'], 'dest_file_id' => $newId, 'dest_parent_id' => $parent, 'source_mtime' => $d['mtime']]
                    );
                } catch (Throwable $e) {
                    report($e);
                    $stats['errors']++;
                }
            }

            // 3) ลบสำเนาที่ไม่ได้ pick แล้ว (เฉพาะเมื่อไม่มี pick ที่ error เพื่อกันลบผิด)
            $hasPickError = $folder->picks()->where('status', 'error')->exists();
            if (!$hasPickError) {
                foreach ($existing as $key => $copy) {
                    if (!isset($desired[$key])) {
                        $this->safeTrash($copy->dest_file_id);
                        $copy->delete();
                        $stats['removed']++;
                    }
                }
            }

            $folder->update([
                'last_synced_at' => now(),
                'last_sync_result' => "เพิ่ม {$stats['added']} | อัปเดต {$stats['updated']} | เอาออก {$stats['removed']} | ผิดพลาด {$stats['errors']}",
            ]);

            return $stats;
        } finally {
            $lock->release();
        }
    }

    private function addDesired(array &$desired, array $file, string $category, array $rel): void
    {
        $name = $file['attributes']['name'] ?? $file['id'];
        $dirs = array_merge([$category], $rel);
        $path = implode('/', array_merge($dirs, [$name]));

        $desired["{$file['id']}|{$path}"] = [
            'source_id' => $file['id'],
            'name' => $name,
            'dirs' => $dirs,
            'path' => $path,
            'mtime' => WorkDriveClient::mtime($file),
        ];
    }

    /** ไล่ไฟล์ในโฟลเดอร์ทั้งหมด (รวมโฟลเดอร์ย่อย) */
    private function walk(string $folderId, array $rel): \Generator
    {
        foreach ($this->wd->children($folderId) as $c) {
            if (WorkDriveClient::isFolder($c)) {
                yield from $this->walk($c['id'], array_merge($rel, [$c['attributes']['name']]));
            } else {
                yield [$c, $rel];
            }
        }
    }

    private function ensurePath(string $root, array $parts): string
    {
        $cur = $root;
        $key = '';
        foreach ($parts as $p) {
            $key .= '/' . $p;
            $cur = $this->pathCache[$key] ??= $this->wd->findOrCreateFolder($cur, $p);
        }

        return $cur;
    }

    private function safeTrash(string $id): void
    {
        try {
            $this->wd->trash($id);
        } catch (Throwable $e) {
            // สำเนาอาจถูกลบด้วยมือไปแล้ว — ข้ามได้
        }
    }
}
