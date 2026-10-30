<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** โฟลเดอร์บริษัทใต้ Accounts ใน WorkDrive (สำเนารายชื่อ ไม่ใช่ข้อมูลต้นฉบับ) */
class WdCompany extends Model
{
    protected $fillable = ['folder_id', 'name'];
}
