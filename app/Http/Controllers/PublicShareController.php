<?php

namespace App\Http\Controllers;

use App\Models\ShareGroup;

/**
 * หน้าสารบัญสำหรับลูกค้า (ลิงก์หลัก) — ไม่ต้อง Login ระบบนี้
 * แสดงแค่ชื่อและลิงก์ไปยัง WorkDrive การเปิดไฟล์จริงยังต้องมีสิทธิ์ใน WorkDrive เสมอ
 */
class PublicShareController extends Controller
{
    public function show(string $token)
    {
        $group = ShareGroup::where('public_token', $token)->firstOrFail();
        $items = $group->sharedPicksByCategory();

        return response()
            ->view('public.share', compact('group', 'items'))
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
