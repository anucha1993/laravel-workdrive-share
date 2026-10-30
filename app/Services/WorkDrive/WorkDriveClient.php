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
 * อ่านอย่างเดียว: ข้อมูลไฟล์/โฟลเดอร์, รายการในโฟลเดอร์, ดาวน์โหลด — ไม่มีคำสั่งแก้ ย้าย ลบ หรือแชร์
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

    /** รายการในโฟลเดอร์ (อ่านอย่างเดียว) — เก็บเฉพาะ id/ชื่อ/ชนิด เพราะ response มีขนาดราว 14 KB ต่อรายการ */
    public function children(string $folderId): array
    {
        $out = [];
        $offset = 0;
        do {
            $page = $this->call('GET', "/files/{$folderId}/files", [
                'page[limit]' => 50,
                'page[offset]' => $offset,
            ])['data'] ?? [];
            foreach ($page as $item) {
                $out[] = self::summary($item);
            }
            $offset += 50;
        } while (count($page) === 50);

        return $out;
    }

    /** ข้อมูลที่ระบบใช้จาก 1 รายการ (response เต็มมีราว 14 KB ต่อรายการ) */
    public static function summary(array $item): array
    {
        $a = $item['attributes'] ?? [];

        return [
            'id' => $item['id'],
            'name' => $a['name'] ?? '',
            'is_folder' => self::isFolder($item),
            'type' => $a['type'] ?? null,
            'extn' => $a['extn'] ?? null,
            'size' => $a['storage_info']['size_in_bytes'] ?? null,
            'modified' => isset($a['modified_time_in_millisecond']) ? (int) $a['modified_time_in_millisecond'] : null,
            'parent_id' => $a['parent_id'] ?? null,
        ];
    }

    /**
     * ดาวน์โหลดไฟล์แบบ stream (ไม่เก็บทั้งไฟล์ไว้ในหน่วยความจำ)
     * ต้องมี scope WorkDrive.files.READ หรือ ZohoFiles.files.READ
     * $format ใช้แปลงไฟล์ของ Zoho (Writer/Sheet/Show) เป็น docx/xlsx/pptx
     */
    public function download(string $id, ?string $format = null): \Psr\Http\Message\ResponseInterface
    {
        $this->pace();
        $url = 'https://download.zoho.' . config('workdrive.dc', 'com') . "/v1/workdrive/download/{$id}";

        $res = Http::withHeaders(['Authorization' => 'Zoho-oauthtoken ' . $this->token()])
            ->withOptions(['stream' => true])
            ->timeout(600)
            ->get($url, $format ? ['format' => $format] : []);

        if (!$res->successful()) {
            throw new WorkDriveException('GET', "download/{$id}", $res->status(),
                ['errors' => [['id' => $res->json('ERROR_MESSAGE'), 'title' => mb_substr($res->body(), 0, 200)]]], $res->body());
        }

        return $res->toPsrResponse();
    }

    public static function isFolder(array $item): bool
    {
        $a = $item['attributes'] ?? [];
        return !empty($a['is_folder']) || ($a['type'] ?? null) === 'folder';
    }
}
