<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharePick extends Model
{
    protected $fillable = [
        'share_group_id', 'category', 'source', 'source_id',
        'source_name', 'source_type', 'permalink', 'status', 'error',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ShareGroup::class, 'share_group_id');
    }

    /** ลิงก์เปิดไฟล์ใน WorkDrive (ใช้ permalink จาก API ถ้ามี) */
    public function url(): string
    {
        return $this->permalink ?: 'https://workdrive.zoho.' . config('workdrive.dc') . "/{$this->source_type}/{$this->source_id}";
    }
}
