<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** สิทธิ์ที่ระบบให้ไว้ใน WorkDrive — เก็บ permission_id เพื่อถอนสิทธิ์ภายหลัง */
class ShareGrant extends Model
{
    protected $fillable = ['share_group_id', 'resource_id', 'email', 'permission_id', 'status', 'error', 'granted_at'];

    protected $casts = ['granted_at' => 'datetime'];
}
