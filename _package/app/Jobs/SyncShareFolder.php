<?php

namespace App\Jobs;

use App\Models\ShareFolder;
use App\Services\WorkDrive\ShareSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncShareFolder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(public ShareFolder $folder)
    {
    }

    public function handle(ShareSyncService $sync): void
    {
        $sync->sync($this->folder->fresh());
    }
}
