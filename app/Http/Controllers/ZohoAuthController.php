<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
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
        $state = Str::random(40);
        $request->session()->put('zoho_oauth_state', $state);

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
        abort_unless(
            $request->filled('state') && hash_equals((string) $request->session()->pull('zoho_oauth_state'), $request->query('state')),
            403,
            'state ไม่ถูกต้อง กรุณาลองใหม่'
        );

        if ($request->filled('error')) {
            return redirect('/')->with('error', 'Zoho ปฏิเสธการเข้าสู่ระบบ: ' . $request->query('error'));
        }

        $accounts = $this->accounts($request->query('accounts-server'));

        $token = Http::asForm()->post("{$accounts}/oauth/v2/token", [
            'grant_type' => 'authorization_code',
            'client_id' => config('workdrive.login.client_id'),
            'client_secret' => config('workdrive.login.client_secret'),
            'redirect_uri' => route('auth.zoho.callback'),
            'code' => $request->query('code'),
        ])->json();

        abort_if(empty($token['access_token']), 401, 'ขอ token จาก Zoho ไม่สำเร็จ');

        // id_token ได้มาจาก token endpoint ของ Zoho โดยตรงผ่าน HTTPS จึงอ่าน claim ได้เลย
        // ถ้าไม่มีอีเมลใน id_token ค่อยถาม userinfo endpoint
        $info = self::jwtClaims($token['id_token'] ?? '');
        if (empty($info['email'])) {
            $info = Http::withHeaders(['Authorization' => 'Zoho-oauthtoken ' . $token['access_token']])
                ->get("{$accounts}/oauth/v2/userinfo")->json() ?? [];
        }

        $email = strtolower($info['email'] ?? '');
        abort_if($email === '', 401, 'ไม่ได้รับอีเมลจาก Zoho');
        abort_if(($info['email_verified'] ?? true) === false, 403, 'อีเมลนี้ยังไม่ได้ยืนยันกับ Zoho');

        // อนุญาตเฉพาะโดเมนขององค์กร
        $allowed = array_filter(array_map('trim', explode(',', (string) config('workdrive.login.allowed_domains'))));
        if ($allowed && !in_array(Str::after($email, '@'), $allowed, true)) {
            abort(403, "บัญชี {$email} ไม่มีสิทธิ์เข้าระบบนี้");
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $info['name'] ?? $email, 'password' => Str::random(40)]
        );

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('workdrive.index'));
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
