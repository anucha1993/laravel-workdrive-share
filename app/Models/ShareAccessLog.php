<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShareAccessLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['share_group_id', 'action', 'resource_id', 'name', 'ip', 'user_agent'];
}
