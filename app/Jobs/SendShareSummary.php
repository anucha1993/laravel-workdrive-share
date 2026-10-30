<?php

namespace App\Jobs;

use App\Models\ShareGroup;
use App\Services\WorkDrive\SharePermissionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** ส่งอีเมลสรุปลิงก์อีกครั้ง (ทุกคน หรือเฉพาะอีเมลที่ระบุ) */
class SendShareSummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ShareGroup $group, public ?array $emails = null)
    {
    }

    public function handle(SharePermissionService $service): void
    {
        $service->sendSummaries($this->group->fresh(), $this->emails);
    }
}
