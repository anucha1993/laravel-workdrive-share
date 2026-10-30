<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharePick extends Model
{
    protected $fillable = [
        'share_folder_id', 'category', 'source', 'source_id',
        'source_name', 'source_type', 'status', 'error',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(ShareFolder::class, 'share_folder_id');
    }
}
