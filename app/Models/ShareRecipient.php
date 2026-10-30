<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareRecipient extends Model
{
    protected $fillable = ['share_group_id', 'email', 'name', 'notified_at'];

    protected $casts = ['notified_at' => 'datetime'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ShareGroup::class, 'share_group_id');
    }
}
