<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShareRun extends Model
{
    protected $fillable = [
        'share_group_id', 'trigger', 'status', 'granted', 'revoked', 'errors',
        'log', 'started_at', 'finished_at',
    ];

    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime'];
}
