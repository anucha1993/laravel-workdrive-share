<?php

use Illuminate\Support\Facades\Schedule;

// ทำงานที่รอคิว (ให้/ถอนสิทธิ์, ส่งอีเมลสรุป) ทุกนาที แล้วหยุดเมื่อคิวว่าง
// บน Plesk ใช้ Scheduled Task ตัวเดียว (schedule:run ทุกนาที) ไม่ต้องมี worker ค้างไว้
Schedule::command('queue:work --stop-when-empty --tries=1 --timeout=3600')->everyMinute()->withoutOverlapping(90);
