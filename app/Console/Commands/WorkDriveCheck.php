<?php

namespace App\Console\Commands;

use App\Services\WorkDrive\WorkDriveClient;
use Illuminate\Console\Command;
use Throwable;

/** ตรวจการตั้งค่า .env และการเชื่อมต่อ WorkDrive (อ่านอย่างเดียว ไม่แชร์/แก้สิ่งใด) */
class WorkDriveCheck extends Command
{
    protected $signature = 'workdrive:check {id* : ลิงก์หรือ ID ไฟล์/โฟลเดอร์ที่อยากลองอ่าน}';

    protected $description = 'ตรวจการเชื่อมต่อ Zoho WorkDrive (อ่านอย่างเดียว)';

    public function handle(WorkDriveClient $wd): int
    {
        foreach (['client_id', 'client_secret', 'refresh_token'] as $k) {
            if (!config("workdrive.{$k}")) {
                $this->error("ยังไม่ได้ตั้งค่า workdrive.{$k} ใน .env");
                return self::FAILURE;
            }
        }

        $ok = true;
        foreach ($this->argument('id') as $text) {
            try {
                $item = $wd->info(WorkDriveClient::extractId($text));
                $a = $item['attributes'] ?? [];
                $this->info(($a['name'] ?? '?') . ' (' . (WorkDriveClient::isFolder($item) ? 'folder' : 'file') . ')');
                $this->line('  permalink = ' . ($a['permalink'] ?? '(ไม่มี)'));
            } catch (Throwable $e) {
                $this->error("{$text}: {$e->getMessage()}");
                $ok = false;
            }
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
