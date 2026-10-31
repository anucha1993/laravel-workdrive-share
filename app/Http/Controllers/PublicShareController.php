<?php

namespace App\Http\Controllers;

use App\Models\ShareAccessLog;
use App\Models\ShareGroup;
use App\Services\WorkDrive\ShareBrowser;
use App\Services\WorkDrive\WorkDriveClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use ZipStream\ZipStream;

/**
 * ลิงก์ลูกค้า (/s/{token}) — หน้าเบราว์เซอร์ไฟล์แบบ WorkDrive ไม่ต้อง Login ระบบนี้
 * - แสดงเฉพาะรายการที่ pick และของที่อยู่ข้างใน (ตรวจทุก request)
 * - ดาวน์โหลดผ่านเซิร์ฟเวอร์นี้ token ของ Zoho ไม่ถูกส่งไปที่ browser
 * - อ่านอย่างเดียว ไม่แก้ ย้าย หรือลบต้นฉบับ
 */
class PublicShareController extends Controller
{
    public function __construct(private ShareBrowser $browser)
    {
    }

    public function show(Request $request, string $token, ?string $category = null)
    {
        $group = $this->group($token);
        if ($blocked = $this->gate($request, $group)) {
            return $blocked;
        }

        $byCategory = $this->browser->rootItems($group);
        if ($category !== null) {
            abort_unless($byCategory->has($category) && $category !== '', 404);
            $items = $byCategory->get($category)->values()->all();
            $folders = [];
        } else {
            $items = $byCategory->get('', collect())->values()->all();
            $folders = $byCategory->keys()->reject(fn ($c) => $c === '')->values()->all();
        }

        $this->log($request, $group, 'view', null, $category);

        return $this->page('public.browser', [
            'group' => $group,
            'token' => $token,
            'crumbs' => $category !== null ? [['name' => $category, 'url' => null]] : [],
            'categories' => $folders,
            'items' => $items,
            'zipUrl' => route('public.zip', $token),
        ]);
    }

    public function folder(Request $request, string $token, string $id)
    {
        $group = $this->group($token);
        if ($blocked = $this->gate($request, $group)) {
            return $blocked;
        }

        $chain = $this->allowed($request, $group, $id);
        $current = end($chain);
        abort_unless($current['is_folder'], 404);

        $this->log($request, $group, 'open', $id, $current['name']);

        return $this->page('public.browser', [
            'group' => $group,
            'token' => $token,
            'crumbs' => array_map(fn ($c) => ['name' => $c['name'], 'url' => route('public.folder', [$token, $c['id']])], $chain),
            'categories' => [],
            'items' => $this->browser->listing($id),
            'zipUrl' => route('public.zip', [$token, $id]),
        ]);
    }

    public function download(Request $request, string $token, string $id, WorkDriveClient $wd): StreamedResponse
    {
        $group = $this->group($token);
        abort_if($this->gate($request, $group), 403);

        $chain = $this->allowed($request, $group, $id);
        $item = end($chain);
        abort_if($item['is_folder'], 404);

        [$name, $format] = $this->browser->downloadName($item);
        try {
            $body = $wd->download($id, $format)->getBody();
        } catch (Throwable $e) {
            report($e);
            abort(502, 'ดาวน์โหลดจาก WorkDrive ไม่สำเร็จ กรุณาลองใหม่ภายหลัง');
        }

        $this->log($request, $group, 'download', $id, $name);

        return response()->streamDownload(function () use ($body) {
            while (!$body->eof()) {
                echo $body->read(1024 * 256);
                flush();
            }
        }, $name, ['Content-Type' => 'application/octet-stream']);
    }

    /** ZIP ทั้งโฟลเดอร์ ($id) หรือทุกรายการที่ pick (ไม่ระบุ $id) — stream ทีละไฟล์ ไม่เก็บไว้บนเซิร์ฟเวอร์ */
    public function zip(Request $request, string $token, WorkDriveClient $wd, ?string $id = null): StreamedResponse
    {
        $group = $this->group($token);
        abort_if($this->gate($request, $group), 403);

        if ($id) {
            $chain = $this->allowed($request, $group, $id);
            $root = end($chain);
            abort_unless($root['is_folder'], 404);
            $zipName = $this->browser->safe($root['name']);
            $sources = [[$root, '']];
        } else {
            $zipName = $this->browser->safe($group->rootName());
            $sources = [];
            foreach ($this->browser->rootItems($group) as $category => $items) {
                foreach ($items as $item) {
                    $sources[] = [$item, $category === '' ? '' : $this->browser->safe($category) . '/'];
                }
            }
        }

        $this->log($request, $group, 'zip', $id, $zipName);
        $browser = $this->browser;

        return response()->stream(function () use ($sources, $wd, $browser) {
            set_time_limit(0);
            $zip = new ZipStream(sendHttpHeaders: false, defaultEnableZeroHeader: true);

            foreach ($sources as [$item, $prefix]) {
                $files = $item['is_folder']
                    ? $browser->walk($item['id'], $prefix . $browser->safe($item['name']) . '/')
                    : [[$item, $prefix . $browser->safe($browser->downloadName($item)[0])]];

                foreach ($files as [$file, $path]) {
                    try {
                        $zip->addFileFromPsr7Stream(
                            fileName: $path,
                            stream: $wd->download($file['id'], $browser->downloadName($file)[1])->getBody(),
                            // ZIP เก็บเวลาแบบไม่มี timezone — ใช้เวลาไทยให้ตรงกับที่เห็นใน WorkDrive
                            lastModificationDateTime: $file['modified'] ? \Illuminate\Support\Carbon::createFromTimestampMs($file['modified'])->timezone(config('app.timezone'))->shiftTimezone('UTC') : null,
                        );
                    } catch (Throwable $e) {
                        report($e);
                        $zip->addFile($path . '.error.txt', 'ดาวน์โหลดไฟล์นี้ไม่สำเร็จ: ' . $e->getMessage());
                    }
                }
            }

            $zip->finish();
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"download.zip\"; filename*=UTF-8''" . rawurlencode("{$zipName}.zip"),
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function unlock(Request $request, string $token)
    {
        $group = $this->group($token);
        if ($group->link_password && Hash::check((string) $request->input('password'), $group->link_password)) {
            $request->session()->put($this->unlockKey($group), true);
            $this->log($request, $group, 'unlock');

            return redirect()->route('public.share', $token);
        }

        $this->log($request, $group, 'unlock_failed');

        return back()->with('error', 'รหัสผ่านไม่ถูกต้อง');
    }

    // ------------------------------------------------------------------
    private function group(string $token): ShareGroup
    {
        abort_if(strlen($token) < 32, 404);

        return ShareGroup::where('public_token', $token)->firstOrFail();
    }

    /** ลิงก์ถูกปิด / หมดอายุ / ต้องใส่รหัสผ่าน → คืนหน้าที่ต้องแสดงแทน */
    private function gate(Request $request, ShareGroup $group)
    {
        if (!$group->linkActive()) {
            return $this->page('public.unavailable', ['group' => $group], 410);
        }
        if ($group->link_password && !$request->session()->get($this->unlockKey($group))) {
            return $this->page('public.password', ['group' => $group, 'token' => $group->public_token]);
        }

        return null;
    }

    /** breadcrumb จากรายการที่ pick ลงมาถึง $id — ถ้าไม่อยู่ใต้รายการที่ pick ให้ 404 */
    private function allowed(Request $request, ShareGroup $group, string $id): array
    {
        abort_unless(preg_match('/^[a-z0-9]{20,64}$/', $id), 404);

        try {
            $chain = $this->browser->locate($group, $id);
        } catch (Throwable) {
            $chain = null;
        }

        if (!$chain) {
            $this->log($request, $group, 'denied', $id);
            abort(404);
        }

        return $chain;
    }

    private function unlockKey(ShareGroup $group): string
    {
        // เปลี่ยนรหัสผ่านแล้วต้องใส่ใหม่
        return 'share-unlock.' . $group->id . '.' . substr(sha1((string) $group->link_password), 0, 12);
    }

    private function log(Request $request, ShareGroup $group, string $action, ?string $resourceId = null, ?string $name = null): void
    {
        ShareAccessLog::create([
            'share_group_id' => $group->id,
            'action' => $action,
            'resource_id' => $resourceId,
            'name' => $name !== null ? mb_substr($name, 0, 250) : null,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);
    }

    private function page(string $view, array $data, int $status = 200)
    {
        return response()->view($view, $data, $status)
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'no-store');
    }
}
