<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ShareGroup extends Model
{
    protected $fillable = [
        'name', 'company_folder_id', 'company_name', 'public_token',
        'link_password', 'link_expires_at', 'link_enabled', 'last_applied_at', 'last_result',
    ];

    protected $hidden = ['link_password'];

    protected $casts = [
        'last_applied_at' => 'datetime',
        'link_expires_at' => 'datetime',
        'link_enabled' => 'boolean',
        'link_password' => 'hashed',
    ];

    public function accessLogs(): HasMany
    {
        return $this->hasMany(ShareAccessLog::class)->latest('id');
    }

    /** ลิงก์ลูกค้าเปิดใช้ได้อยู่หรือไม่ (ไม่ถูกปิด และยังไม่หมดอายุ) */
    public function linkActive(): bool
    {
        return $this->link_enabled && (!$this->link_expires_at || $this->link_expires_at->isFuture());
    }

    /** ชื่อที่แสดงเป็นโฟลเดอร์บนสุดของลิงก์ลูกค้า */
    public function rootName(): string
    {
        return $this->company_name ?: $this->name;
    }

    public function picks(): HasMany
    {
        return $this->hasMany(SharePick::class)->orderBy('category')->orderBy('id');
    }

    public function grants(): HasMany
    {
        return $this->hasMany(ShareGrant::class);
    }

    /** ลิงก์หลัก (หน้าสารบัญ) — สร้างรหัสให้ครั้งแรกที่เรียกใช้ */
    public function publicUrl(): string
    {
        if (!$this->public_token) {
            $this->regeneratePublicToken();
        }

        return route('public.share', $this->public_token);
    }

    /** สร้างรหัสใหม่ ลิงก์เก่าจะใช้ไม่ได้ทันที */
    public function regeneratePublicToken(): void
    {
        $this->update(['public_token' => Str::random(40)]);
    }

    /** ทุกรายการที่ pick ไว้ แยกตามหมวด (ใช้บนหน้าลิงก์รวม — การแชร์สิทธิ์ทำใน Zoho เอง) */
    public function picksByCategory(): Collection
    {
        return $this->picks()->get()->groupBy('category');
    }
}
