<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Login ด้วยบัญชี Zoho (OpenID Connect ตามเอกสาร "Sign in using Zoho")
 * ถ้าผู้ใช้ล็อกอิน Zoho ในเบราว์เซอร์อยู่แล้ว กดลิงก์ /auth/zoho ครั้งเดียวก็เข้าระบบได้เลย
 */
class ZohoAuthController extends Controller
{
    private function accounts(?string $server = null): string
    {
        // Zoho ส่ง accounts-server กลับมาใน callback (กรณีบัญชีอยู่ต่าง DC) — รับเฉพาะโดเมน zoho จริง
        if ($server && preg_match('#^https://accounts\.zoho\.[a-z.]+$#', $server)) {
            return $server;
        }
        return 'https://accounts.zoho.' . config('workdrive.dc', 'com');
    }

    public function redirect(Request $request)
    {
        if (Auth::check()) {
            return redirect()->intended(route('workdrive.index'));
        }

        // จำไว้หลายค่า เผื่อผู้ใช้เปิด /auth/zoho หลายแท็บพร้อมกัน
        $state = Str::random(40);
        $states = array_slice((array) $request->session()->get('zoho_oauth_states', []), -4);
        $request->session()->put('zoho_oauth_states', [...$states, $state]);

        return redirect()->away($this->accounts() . '/oauth/v2/auth?' . http_build_query([
            'scope' => 'openid email profile',
            'client_id' => config('workdrive.login.client_id'),
            'response_type' => 'code',
            'redirect_uri' => route('auth.zoho.callback'),
            'access_type' => 'online',
            'state' => $state,
        ]));
    }

    public function callback(Request $request)
    {
        // กดย้อนกลับ/รีเฟรชหน้านี้หลัง Login สำเร็จแล้ว
        if (Auth::check()) {
            return redirect()->route('workdrive.index');
        }

        $states = (array) $request->session()->get('zoho_oauth_states', []);
        $given = (string) $request->query('state');
        $match = array_filter($states, fn ($s) => hash_equals((string) $s, $given));
        if ($given === '' || !$match) {
            // code/state ใช้ได้ครั้งเดียว — ให้เริ่มใหม่แทนการขึ้น 403 เฉยๆ
            return $this->fail('ลิงก์เข้าสู่ระบบนี้หมดอายุหรือถูกใช้ไปแล้ว (เกิดได้เมื่อรีเฟรชหน้า หรือเปิดหน้า Login หลายแท็บพร้อมกัน)', 403);
        }
        $request->session()->put('zoho_oauth_states', array_values(array_diff($states, $match)));

        if ($request->filled('error')) {
            return $this->fail('Zoho ปฏิเสธการเข้าสู่ระบบ: ' . $request->query('error'), 403);
        }

        $accounts = $this->accounts($request->query('accounts-server'));

        $token = Http::asForm()->post("{$accounts}/oauth/v2/token", [
            'grant_type' => 'authorization_code',
            'client_id' => config('workdrive.login.client_id'),
            'client_secret' => config('workdrive.login.client_secret'),
            'redirect_uri' => route('auth.zoho.callback'),
            'code' => $request->query('code'),
        ])->json();

        if (empty($token['access_token'])) {
            // Zoho ตอบ error เป็นรหัส เช่น invalid_client (Client ID/Secret ผิด), invalid_code (code หมดอายุ),
            // invalid_redirect_uri — ไม่มีข้อมูลลับในนี้ แสดงและบันทึกได้
            $code = $token['error'] ?? 'unknown';
            Log::warning('Zoho login: แลก token ไม่สำเร็จ', ['error' => $code, 'accounts' => $accounts]);

            return $this->fail("ขอ token จาก Zoho ไม่สำเร็จ ({$code})", 401);
        }

        // id_token ได้มาจาก token endpoint ของ Zoho โดยตรงผ่าน HTTPS จึงอ่าน claim ได้เลย
        // ถ้าไม่มีอีเมลใน id_token ค่อยถาม userinfo endpoint
        $info = self::jwtClaims($token['id_token'] ?? '');
        if (empty($info['email'])) {
            $info = Http::withHeaders(['Authorization' => 'Zoho-oauthtoken ' . $token['access_token']])
                ->get("{$accounts}/oauth/v2/userinfo")->json() ?? [];
        }

        $email = strtolower($info['email'] ?? '');
        if ($email === '') {
            Log::warning('Zoho login: ไม่ได้รับอีเมล', ['claims' => array_keys($info)]);
            return $this->fail('ไม่ได้รับอีเมลจาก Zoho', 401);
        }
        if (($info['email_verified'] ?? true) === false) {
            return $this->fail('อีเมลนี้ยังไม่ได้ยืนยันกับ Zoho', 403);
        }

        // อนุญาตเฉพาะโดเมนขององค์กร
        $allowed = array_filter(array_map('trim', explode(',', (string) config('workdrive.login.allowed_domains'))));
        if ($allowed && !in_array(Str::after($email, '@'), $allowed, true)) {
            return $this->fail("บัญชี {$email} ไม่มีสิทธิ์เข้าระบบนี้ (รับเฉพาะ " . implode(', ', $allowed) . ')', 403);
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $info['name'] ?? $email, 'password' => Str::random(40)]
        );

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('workdrive.index'));
    }

    /** หน้าแจ้งปัญหาพร้อมปุ่มลองใหม่ (production ซ่อนข้อความของ abort() จึงใช้หน้านี้แทน) */
    private function fail(string $message, int $status)
    {
        return response()->view('workdrive.login-retry', ['message' => $message], $status);
    }

    private static function jwtClaims(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return [];
        }

        return json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true) ?: [];
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
