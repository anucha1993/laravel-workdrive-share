<?php

namespace App\Console\Commands;

use App\Jobs\RefreshCompanies;
use App\Models\WdCompany;
use Illuminate\Console\Command;

class WorkDriveCompanies extends Command
{
    protected $signature = 'workdrive:companies';

    protected $description = 'อ่านรายชื่อโฟลเดอร์บริษัทใต้ Accounts มาเก็บไว้ (ใช้เวลาหลายนาที)';

    public function handle(): int
    {
        if (!config('workdrive.company_root_id')) {
            $this->error('ยังไม่ได้ตั้งค่า WORKDRIVE_COMPANY_ROOT_ID ใน .env');
            return self::FAILURE;
        }

        $this->info('กำลังอ่านรายชื่อบริษัท...');
        RefreshCompanies::dispatchSync();
        $this->info('มีบริษัท ' . WdCompany::count() . ' รายการ');

        return self::SUCCESS;
    }
}
