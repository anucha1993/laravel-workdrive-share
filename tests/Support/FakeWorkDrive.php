<?php

namespace Tests\Support;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * WorkDrive จำลองในหน่วยความจำ สำหรับใช้กับ Http::fake
 * รูปแบบ request/response อ้างอิงเอกสาร WorkDrive API v1 (files, permissions)
 */
class FakeWorkDrive
{
    /** @var array<string, array{id:string,name:string,folder:bool,status:int,parent:?string}> */
    public array $items = [];

    /** สิทธิ์ที่มีอยู่ใน WorkDrive: permission_id => [resource_id, email, role_id, notify] */
    public array $permissions = [];

    /** @var array<int, array{method:string,path:string,body:array}> */
    public array $requests = [];

    /** อีเมลที่ WorkDrive ปฏิเสธการแชร์ (จำลองแอดมินไม่อนุญาตโดเมนนี้) */
    public array $blockedEmails = [];

    private int $seq = 0;

    public static function id(string $prefix): string
    {
        return str_pad($prefix, 25, '0');
    }

    public function addFolder(string $id, string $name, ?string $parent = null): string
    {
        $this->items[$id] = ['id' => $id, 'name' => $name, 'folder' => true, 'status' => 1, 'parent' => $parent];
        return $id;
    }

    public function addFile(string $id, string $name, ?string $parent = null): string
    {
        $this->items[$id] = ['id' => $id, 'name' => $name, 'folder' => false, 'status' => 1, 'parent' => $parent];
        return $id;
    }

    /** สิทธิ์ที่มีอยู่จริงตอนนี้ ในรูป "resource|email" */
    public function shared(): array
    {
        $out = array_map(fn ($p) => "{$p['resource_id']}|{$p['email']}", $this->permissions);
        sort($out);
        return $out;
    }

    public function writes(): array
    {
        return array_values(array_filter($this->requests, fn ($r) => $r['method'] !== 'GET'));
    }

    public function fake(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/oauth/v2/token')) {
                return Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]);
            }

            $path = substr(parse_url($request->url(), PHP_URL_PATH), strlen('/workdrive/api/v1'));
            $method = $request->method();
            $body = $request->data();
            $this->requests[] = ['method' => $method, 'path' => $path, 'body' => $body];

            // GET /files/{id}/files — รายการในโฟลเดอร์ (แบ่งหน้าละ 50)
            if ($method === 'GET' && preg_match('#^/files/(\w+)/files$#', $path, $m)) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
                $kids = array_values(array_filter($this->items, fn ($i) => $i['parent'] === $m[1] && $i['status'] === 1));
                $page = array_slice($kids, (int) ($q['page']['offset'] ?? 0), (int) ($q['page']['limit'] ?? 50));
                return Http::response(['data' => array_map(fn ($i) => ['id' => $i['id'], 'type' => 'files', 'attributes' => [
                    'name' => $i['name'], 'is_folder' => $i['folder'], 'type' => $i['folder'] ? 'folder' : 'pdf',
                ]], $page)]);
            }

            // GET /files/{id}
            if ($method === 'GET' && preg_match('#^/files/(\w+)$#', $path, $m)) {
                $i = $this->items[$m[1]] ?? null;
                if (!$i || $i['status'] !== 1) {
                    return Http::response(['errors' => [['id' => 'R008', 'title' => 'The resource does not exist']]], 404);
                }
                return Http::response(['data' => ['id' => $i['id'], 'type' => 'files', 'attributes' => [
                    'name' => $i['name'], 'is_folder' => $i['folder'], 'type' => $i['folder'] ? 'folder' : 'pdf',
                    'permalink' => "https://workdrive.zoho.com/" . ($i['folder'] ? 'folder' : 'file') . "/{$i['id']}",
                    'parent_id' => $i['parent'] ?? null,
                ]]]);
            }

            // POST /permissions
            if ($method === 'POST' && $path === '/permissions') {
                $a = $body['data']['attributes'];
                if (in_array($a['email_id'], $this->blockedEmails, true)) {
                    return Http::response(['errors' => [['id' => 'R910', 'title' => 'External User Email share is not allowed']]], 401);
                }
                $id = 'perm' . ++$this->seq;
                $this->permissions[$id] = ['resource_id' => $a['resource_id'], 'email' => $a['email_id'],
                    'role_id' => (string) $a['role_id'], 'notify' => $a['send_notification_mail']];
                return Http::response(['data' => ['id' => $id, 'type' => 'permissions', 'attributes' => $a + ['permissionid' => $id]]]);
            }

            // DELETE /permissions/{id}
            if ($method === 'DELETE' && preg_match('#^/permissions/(\w+)$#', $path, $m)) {
                if (!isset($this->permissions[$m[1]])) {
                    return Http::response(['errors' => [['id' => 'R008', 'title' => 'The resource does not exist']]], 404);
                }
                unset($this->permissions[$m[1]]);
                return Http::response(null, 200);
            }

            return Http::response(['errors' => [['title' => "ไม่รองรับ {$method} {$path}"]]], 400);
        });
    }
}
