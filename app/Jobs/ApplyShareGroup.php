<?php

namespace App\Jobs;

use App\Models\ShareGroup;
use App\Services\WorkDrive\SharePermissionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** ให้/ถอนสิทธิ์ของกลุ่มแชร์ แล้วส่งอีเมลสรุปให้คนที่ได้สิทธิ์ใหม่ */
class ApplyShareGroup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(
        public ShareGroup $group,
        public string $trigger = 'manual',
        public bool $notify = true,
        public bool $deleteGroupWhenEmpty = false,
    ) {
    }

    public function handle(SharePermissionService $service): void
    {
        $group = $this->group->fresh();
        $result = $service->apply($group, $this->trigger);

        if ($this->notify && $result['newly_granted']) {
            $service->sendSummaries($group, $result['newly_granted']);
        }

        // ลบกลุ่มได้เมื่อถอนสิทธิ์ครบทุกรายการแล้วเท่านั้น
        if ($this->deleteGroupWhenEmpty && !$group->grants()->exists()) {
            $group->delete();
        }
    }
}
