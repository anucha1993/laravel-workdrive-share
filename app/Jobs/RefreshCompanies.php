<?php

namespace App\Jobs;

use App\Models\WdCompany;
use App\Services\WorkDrive\WorkDriveClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

/** อ่านรายชื่อโฟลเดอร์บริษัทใต้ Accounts มาเก็บไว้ (2,600+ รายการ ใช้เวลาหลายนาที) */
class RefreshCompanies implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 1800;
    public int $tries = 1;

    public function handle(WorkDriveClient $wd): void
    {
        $rootId = config('workdrive.company_root_id');
        if (!$rootId) {
            return;
        }

        $folders = array_filter($wd->children($rootId), fn ($c) => $c['is_folder']);
        if (!$folders) {
            return; // ไม่ลบรายชื่อเดิมถ้าอ่านไม่ได้
        }

        foreach (array_chunk($folders, 500) as $chunk) {
            WdCompany::upsert(
                array_map(fn ($c) => ['folder_id' => $c['id'], 'name' => trim($c['name']), 'created_at' => now(), 'updated_at' => now()], $chunk),
                ['folder_id'],
                ['name', 'updated_at']
            );
        }
        WdCompany::whereNotIn('folder_id', array_column($folders, 'id'))->delete();

        Cache::put('wd-companies-refreshed-at', now());
    }
}
