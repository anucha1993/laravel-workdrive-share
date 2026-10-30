<?php

namespace App\Console\Commands;

use App\Models\ShareFolder;
use App\Services\WorkDrive\ShareSyncService;
use Illuminate\Console\Command;
use Throwable;

class WorkDriveSync extends Command
{
    protected $signature = 'workdrive:sync {folder? : ชื่อหรือ ID ของโฟลเดอร์แชร์ (เว้นว่าง = ทั้งหมด)}';

    protected $description = 'ซิงก์ไฟล์ที่ pick ไว้ไปยังโฟลเดอร์แชร์ใน Zoho WorkDrive';

    public function handle(ShareSyncService $sync): int
    {
        $arg = $this->argument('folder');
        $folders = $arg
            ? ShareFolder::where('id', $arg)->orWhere('name', $arg)->get()
            : ShareFolder::all();

        foreach ($folders as $folder) {
            try {
                $s = $sync->sync($folder);
                $this->info("{$folder->name}: " . ($s['skipped'] ?? $folder->fresh()->last_sync_result));
            } catch (Throwable $e) {
                $this->error("{$folder->name}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
