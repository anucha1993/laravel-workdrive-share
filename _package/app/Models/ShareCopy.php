<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShareCopy extends Model
{
    protected $fillable = [
        'share_folder_id', 'source_file_id', 'path', 'name',
        'dest_file_id', 'dest_parent_id', 'source_mtime',
    ];
}
