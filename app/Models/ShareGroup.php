<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShareGroup extends Model
{
    protected $fillable = ['name', 'company_folder_id', 'company_name', 'last_applied_at', 'last_result'];

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
}
