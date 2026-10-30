<?php

namespace App\Services\WorkDrive;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WorkDriveClient
{
    private string $base;
    private string $accounts;

    public function __construct()
    {
        $dc = config('workdrive.dc', 'com');
        $this->base = "https://www.zohoapis.{$dc}/workdrive/api/v1";
        $this->accounts = "https://accounts.zoho.{$dc}";
    }

    /** รับได้ทั้ง ID ตรงๆ หรือ URL ที่ copy จาก WorkDrive */
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
            ->retry(4, 2000, function ($e) {
                return $e instanceof ConnectionException
                    || ($e instanceof RequestException && in_array($e->response->status(), [429, 500, 502, 503, 504]));
            });
    }

    private function call(string $method, string $path, array $data = []): array
    {
        try {
            $res = $method === 'GET'
                ? $this->http()->get($path, $data)
                : $this->http()->send($method, $path, ['json' => $data]);
        } catch (RequestException $e) {
            throw new RuntimeException("{$method} {$path} -> {$e->response->status()}: " . mb_substr($e->response->body(), 0, 400));
        }

        return $res->json() ?? [];
    }

    // ------------------------------------------------------------ read
    public function info(string $id): array
    {
        return $this->call('GET', "/files/{$id}")['data'];
    }

    public function children(string $folderId): array
    {
        $out = [];
        $offset = 0;
        do {
            $page = $this->call('GET', "/files/{$folderId}/files", [
                'page[limit]' => 50,
                'page[offset]' => $offset,
            ])['data'] ?? [];
            $out = array_merge($out, $page);
            $offset += 50;
        } while (count($page) === 50);

        return $out;
    }

    public static function isFolder(array $item): bool
    {
        $a = $item['attributes'] ?? [];
        return !empty($a['is_folder']) || ($a['type'] ?? null) === 'folder';
    }

    public static function mtime(array $item): string
    {
        $a = $item['attributes'] ?? [];
        return (string) ($a['modified_time_in_millisecond'] ?? $a['modified_time'] ?? '');
    }

    // ------------------------------------------------------------ write
    public function findOrCreateFolder(string $parentId, string $name): string
    {
        foreach ($this->children($parentId) as $c) {
            if (self::isFolder($c) && ($c['attributes']['name'] ?? null) === $name) {
                return $c['id'];
            }
        }

        return $this->call('POST', '/files', ['data' => [
            'attributes' => ['name' => $name, 'parent_id' => $parentId],
            'type' => 'files',
        ]])['data']['id'];
    }

    /** คัดลอกบนเซิร์ฟเวอร์ Zoho (ไม่ดาวน์โหลด) คืนค่า ID ของสำเนา */
    public function copy(string $sourceId, string $destParentId): string
    {
        $d = $this->call('POST', "/files/{$destParentId}/copy", ['data' => [
            'attributes' => ['resource_id' => $sourceId],
            'type' => 'files',
        ]])['data'];
        $d = array_is_list($d) ? $d[0] : $d;

        return $d['attributes']['resource_id'] ?? $d['id'];
    }

    /** ย้ายลงถังขยะ (status 51) */
    public function trash(string $id): void
    {
        $this->call('PATCH', "/files/{$id}", ['data' => [
            'attributes' => ['status' => '51'],
            'type' => 'files',
        ]]);
    }

    public function shareEmail(string $resourceId, string $email, ?string $roleId = null, bool $notify = true): array
    {
        return $this->call('POST', '/permissions', ['data' => [
            'attributes' => [
                'resource_id' => $resourceId,
                'shared_type' => 'personal',
                'email_id' => $email,
                'role_id' => $roleId ?? config('workdrive.email_role_id'),
                'send_notification_mail' => $notify,
            ],
            'type' => 'permissions',
        ]]);
    }

    public function createLink(string $resourceId, string $name, ?string $password = null, ?string $expire = null, bool $allowDownload = true): ?string
    {
        $attrs = [
            'resource_id' => $resourceId,
            'link_name' => $name,
            'role_id' => config('workdrive.link_role_id'),
            'allow_download' => $allowDownload,
            'request_user_data' => false,
        ];
        if ($password) {
            $attrs['password_text'] = $password;
        }
        if ($expire) {
            $attrs['expiration_date'] = $expire;
        }

        return $this->call('POST', '/links', ['data' => ['attributes' => $attrs, 'type' => 'links']])['data']['attributes']['link'] ?? null;
    }
}
