<?php

namespace App\Console\Commands;

use App\Models\ShareGroup;
use App\Services\WorkDrive\SharePermissionService;
use Illuminate\Console\Command;
use Throwable;

class WorkDriveApply extends Command
{
    protected $signature = 'workdrive:apply {group? : ชื่อหรือ ID ของกลุ่มแชร์ (เว้นว่าง = ทั้งหมด)} {--no-mail : ไม่ส่งอีเมลสรุป}';

    protected $description = 'ให้/ถอนสิทธิ์แชร์บนไฟล์ต้นฉบับตามรายการในกลุ่มแชร์';

    public function handle(SharePermissionService $service): int
    {
        $arg = $this->argument('group');
        $groups = $arg ? ShareGroup::where('id', $arg)->orWhere('name', $arg)->get() : ShareGroup::all();

        foreach ($groups as $group) {
            try {
                $r = $service->apply($group, 'cli');
                $sent = (!$this->option('no-mail') && $r['newly_granted']) ? $service->sendSummaries($group, $r['newly_granted']) : 0;
                $this->info("{$group->name}: " . ($r['skipped'] ?? $group->fresh()->last_result) . " | ส่งอีเมล {$sent}");
            } catch (Throwable $e) {
                $this->error("{$group->name}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
