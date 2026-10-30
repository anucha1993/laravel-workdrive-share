<?php

namespace App\Services\WorkDrive;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * เรียก Zoho WorkDrive REST API v1
 * ใช้เฉพาะ: อ่านข้อมูลไฟล์/โฟลเดอร์, เพิ่มสิทธิ์แชร์, ถอนสิทธิ์แชร์ — ไม่มีคำสั่งแก้ ย้าย หรือลบไฟล์
 */
class WorkDriveClient
{
    private string $base;
    private string $accounts;
    private float $lastCallAt = 0;

    public function __construct()
    {
        $dc = config('workdrive.dc', 'com');
        $this->base = "https://www.zohoapis.{$dc}/workdrive/api/v1";
        $this->accounts = "https://accounts.zoho.{$dc}";
    }

    /** รับได้ทั้ง ID ตรงๆ หรือ URL ที่ copy จาก WorkDrive (ใช้ส่วนท้ายสุด) */
    public static function extractId(string $text): string
    {
        preg_match_all('/[a-z0-9]{20,}/', trim($text), $m);
        if (empty($m[0])) {
            throw new RuntimeException("หา ID ไม่เจอใน: {$text}");
        }
        return end($m[0]);
    }

    // ------------------------------------------------------------ auth
    private function token(): string
    {
        $cached = Cache::get('workdrive.access_token');
        if ($cached) {
            return $cached;
        }

        $res = Http::asForm()->post("{$this->accounts}/oauth/v2/token", [
            'refresh_token' => config('workdrive.refresh_token'),
            'client_id' => config('workdrive.client_id'),
            'client_secret' => config('workdrive.client_secret'),
            'grant_type' => 'refresh_token',
        ])->json();

        if (empty($res['access_token'])) {
            throw new RuntimeException('ขอ access token ไม่สำเร็จ: ' . json_encode($res));
        }

        $ttl = max(60, (int) ($res['expires_in'] ?? 3600) - 120);
        Cache::put('workdrive.access_token', $res['access_token'], $ttl);

        return $res['access_token'];
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->base)
            ->withHeaders([
                'Authorization' => 'Zoho-oauthtoken ' . $this->token(),
                'Accept' => 'application/vnd.api+json',
            ])
            ->asJson()
            ->timeout(60)
            // ชนลิมิต (429) หรือเซิร์ฟเวอร์ขัดข้อง → รอแล้วลองใหม่ 5, 10, 20 วินาที
            ->retry([5000, 10000, 20000], 0, function ($e) {
                return $e instanceof ConnectionException
                    || ($e instanceof RequestException && in_array($e->response->status(), [429, 502, 503, 504]));
            });
    }

    /** เว้นระยะระหว่างคำขอ กันชนลิมิต API */
    private function pace(): void
    {
        $delay = config('workdrive.request_delay_ms', 0) / 1000;
        $wait = $this->lastCallAt + $delay - microtime(true);
        if ($wait > 0) {
            usleep((int) ($wait * 1_000_000));
        }
        $this->lastCallAt = microtime(true);
    }

    private function call(string $method, string $path, array $data = []): array
    {
        $this->pace();

        try {
            $res = $method === 'GET'
                ? $this->http()->get($path, $data)
                : $this->http()->send($method, $path, $data ? ['json' => $data] : []);
        } catch (RequestException $e) {
            throw new WorkDriveException($method, $path, $e->response->status(), $e->response->json() ?? [], $e->response->body());
        }

        return $res->json() ?? [];
    }

    // ------------------------------------------------------------ read
    public function info(string $id): array
    {
        return $this->call('GET', "/files/{$id}")['data'];
    }

    public static function isFolder(array $item): bool
    {
        $a = $item['attributes'] ?? [];
        return !empty($a['is_folder']) || ($a['type'] ?? null) === 'folder';
    }

    // ------------------------------------------------------------ sharing
    /** ให้สิทธิ์ดูอย่างเดียวแก่อีเมล คืนค่า permission_id */
    public function grantView(string $resourceId, string $email, bool $isFolder): string
    {
        $res = $this->call('POST', '/permissions', ['data' => [
            'type' => 'permissions',
            'attributes' => [
                'resource_id' => $resourceId,
                'shared_type' => 'personal',
                'email_id' => $email,
                'role_id' => $isFolder ? config('workdrive.folder_view_role_id') : config('workdrive.file_view_role_id'),
                'send_notification_mail' => (bool) config('workdrive.zoho_notification'),
            ],
        ]]);

        $d = $res['data'] ?? [];
        $d = array_is_list($d) ? ($d[0] ?? []) : $d;
        $id = $d['id'] ?? $d['attributes']['permissionid'] ?? null;
        if (!$id) {
            throw new RuntimeException('ไม่พบ permission id ใน response: ' . mb_substr(json_encode($res, JSON_UNESCAPED_UNICODE), 0, 300));
        }

        return $id;
    }

    /** ถอนสิทธิ์ที่เคยให้ไว้ */
    public function revoke(string $permissionId): void
    {
        $this->call('DELETE', "/permissions/{$permissionId}");
    }
}
