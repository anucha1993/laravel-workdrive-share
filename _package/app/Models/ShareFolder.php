<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShareFolder extends Model
{
    protected $fillable = ['name', 'wd_folder_id', 'share_link', 'last_synced_at', 'last_sync_result'];

    protected $casts = ['last_synced_at' => 'datetime'];

    public function picks(): HasMany
    {
        return $this->hasMany(SharePick::class)->orderBy('category');
    }

    public function copies(): HasMany
    {
        return $this->hasMany(ShareCopy::class);
    }
}
