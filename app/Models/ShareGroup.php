<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ShareGroup extends Model
{
    protected $fillable = ['name', 'company_folder_id', 'company_name', 'public_token', 'last_applied_at', 'last_result'];

    protected $casts = ['last_applied_at' => 'datetime'];

    public function picks(): HasMany
    {
        return $this->hasMany(SharePick::class)->orderBy('category')->orderBy('id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(ShareRecipient::class)->orderBy('email');
    }

    public function grants(): HasMany
    {
        return $this->hasMany(ShareGrant::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ShareRun::class)->latest('id');
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

    /** รายการที่แชร์สิทธิ์สำเร็จแล้วอย่างน้อย 1 คน แยกตามหมวด (ใช้บนหน้าสารบัญ) */
    public function sharedPicksByCategory(): Collection
    {
        $granted = $this->grants()->where('status', 'granted')->distinct()->pluck('resource_id')->flip();

        return $this->picks()->get()->filter(fn ($p) => $granted->has($p->source_id))->groupBy('category');
    }
}
