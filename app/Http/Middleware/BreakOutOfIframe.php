<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * หน้า login ของ Zoho ไม่ยอมให้แสดงใน iframe (X-Frame-Options: SAMEORIGIN)
 * และ cookie ของระบบจะไม่ถูกส่งใน iframe ข้ามโดเมน
 * ถ้าถูกเปิดใน iframe (เช่น Web Tab ของ Zoho CRM) ให้แสดงปุ่ม "เปิดในแท็บใหม่" แทน
 */
class BreakOutOfIframe
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('GET') && $request->header('Sec-Fetch-Dest') === 'iframe') {
            return response()->view('workdrive.open-new-tab', ['url' => $request->fullUrl()]);
        }

        return $next($request);
    }
}
