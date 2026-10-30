<?php

namespace Tests\Support;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * WorkDrive จำลองในหน่วยความจำ สำหรับใช้กับ Http::fake
 * รูปแบบ request/response อ้างอิงเอกสาร WorkDrive API v1 (files, download)
 */
class FakeWorkDrive
{
    /** @var array<string, array{id:string,name:string,folder:bool,status:int,parent:?string,type:string,extn:string}> */
    public array $items = [];

    /** @var array<int, array{method:string,path:string,query:array}> */
    public array $requests = [];

    /** ให้ download ตอบ INVALID_OAUTHSCOPE (จำลอง token ไม่มี scope ดาวน์โหลด) */
    public bool $downloadScopeMissing = false;

    public static function id(string $prefix): string
    {
        return str_pad($prefix, 25, '0');
    }

    public function addFolder(string $id, string $name, ?string $parent = null): string
    {
        $this->items[$id] = ['id' => $id, 'name' => $name, 'folder' => true, 'status' => 1, 'parent' => $parent, 'type' => 'folder', 'extn' => ''];
        return $id;
    }

    public function addFile(string $id, string $name, ?string $parent = null, string $type = 'pdf'): string
    {
        $extn = $type === 'writer' ? '' : strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $this->items[$id] = ['id' => $id, 'name' => $name, 'folder' => false, 'status' => 1, 'parent' => $parent, 'type' => $type, 'extn' => $extn];
        return $id;
    }

    public static function content(string $id, ?string $format = null): string
    {
        return "content-of-{$id}" . ($format ? ".{$format}" : '');
    }

    public function writes(): array
    {
        return array_values(array_filter($this->requests, fn ($r) => $r['method'] !== 'GET'));
    }

    private function attributes(array $i): array
    {
        return [
            'name' => $i['name'], 'is_folder' => $i['folder'], 'type' => $i['type'], 'extn' => $i['extn'],
            'parent_id' => $i['parent'], 'modified_time_in_millisecond' => 1760000000000,
            'storage_info' => ['size_in_bytes' => $i['folder'] ? 0 : 1234],
            'permalink' => 'https://workdrive.zoho.com/' . ($i['folder'] ? 'folder' : 'file') . "/{$i['id']}",
        ];
    }

    public function fake(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/oauth/v2/token')) {
                return Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]);
            }

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
            $method = $request->method();
            $full = parse_url($request->url(), PHP_URL_PATH);
            $this->requests[] = ['method' => $method, 'path' => $full, 'query' => $q];

            // GET https://download.zoho.com/v1/workdrive/download/{id}
            if ($method === 'GET' && preg_match('#^/v1/workdrive/download/(\w+)$#', $full, $m)) {
                if ($this->downloadScopeMissing) {
                    return Http::response(['ERROR_MESSAGE' => 'INVALID_OAUTHSCOPE', 'ERROR_CODE' => 401], 401);
                }
                $i = $this->items[$m[1]] ?? null;
                return $i && !$i['folder'] ? Http::response(self::content($i['id'], $q['format'] ?? null)) : Http::response('', 404);
            }

            $path = substr($full, strlen('/workdrive/api/v1'));

            // GET /files/{id}/files — รายการในโฟลเดอร์ (แบ่งหน้าละ 50)
            if ($method === 'GET' && preg_match('#^/files/(\w+)/files$#', $path, $m)) {
                $kids = array_values(array_filter($this->items, fn ($i) => $i['parent'] === $m[1] && $i['status'] === 1));
                $page = array_slice($kids, (int) ($q['page']['offset'] ?? 0), (int) ($q['page']['limit'] ?? 50));
                return Http::response(['data' => array_map(fn ($i) => ['id' => $i['id'], 'type' => 'files', 'attributes' => $this->attributes($i)], $page)]);
            }

            // GET /files/{id}
            if ($method === 'GET' && preg_match('#^/files/(\w+)$#', $path, $m)) {
                $i = $this->items[$m[1]] ?? null;
                if (!$i || $i['status'] !== 1) {
                    return Http::response(['errors' => [['id' => 'R008', 'title' => 'The resource does not exist']]], 404);
                }
                return Http::response(['data' => ['id' => $i['id'], 'type' => 'files', 'attributes' => $this->attributes($i)]]);
            }

            return Http::response(['errors' => [['title' => "ไม่รองรับ {$method} {$full}"]]], 400);
        });
    }
}
