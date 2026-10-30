<?php

namespace App\Http\Controllers;

use App\Jobs\RefreshCompanies;
use App\Models\ShareGroup;
use App\Models\SharePick;
use App\Models\WdCompany;
use App\Services\WorkDrive\PickImporter;
use App\Services\WorkDrive\ShareBrowser;
use App\Services\WorkDrive\WorkDriveClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

/** ฝั่งพนักงาน: เลือกลูกค้า, pick รายการ, ตั้งค่าลิงก์ลูกค้า (อ่าน WorkDrive อย่างเดียว) */
class ShareGroupController extends Controller
{
    public function index()
    {
        $groups = ShareGroup::withCount('picks')->orderBy('name')->get();
        $companies = WdCompany::orderBy('name')->pluck('name');
        $companiesRefreshedAt = Cache::get('wd-companies-refreshed-at');

        return view('workdrive.index', compact('groups', 'companies', 'companiesRefreshedAt'));
    }

    /** สร้างลิงก์ลูกค้าจากโฟลเดอร์ลูกค้าใต้ Accounts — ไม่สร้างอะไรใน WorkDrive */
    public function store(Request $request, WorkDriveClient $wd)
    {
        $data = $request->validate([
            'company' => 'required|string|max:1000',
            'name' => 'nullable|string|max:200',
        ]);

        $input = trim($data['company']);
        $company = WdCompany::where('name', $input)->first();

        // ลูกค้าใหม่ที่ยังไม่อยู่ในรายชื่อ: วางลิงก์โฟลเดอร์ลูกค้าได้เลย (อ่านจาก Zoho ทันที 1 ครั้ง)
        if (!$company && (str_contains($input, 'workdrive.zoho') || preg_match('/^[a-z0-9]{25,}$/', $input))) {
            try {
                $item = $wd->info(WorkDriveClient::extractId($input));
            } catch (Throwable $e) {
                return back()->withInput()->with('error', 'อ่านโฟลเดอร์จากลิงก์ไม่ได้: ' . $e->getMessage());
            }
            if (!WorkDriveClient::isFolder($item) || ($item['attributes']['parent_id'] ?? null) !== config('workdrive.company_root_id')) {
                return back()->withInput()->with('error', 'ลิงก์นี้ไม่ใช่โฟลเดอร์ลูกค้าที่อยู่ใต้ Accounts');
            }
            $company = WdCompany::updateOrCreate(['folder_id' => $item['id']], ['name' => trim($item['attributes']['name'])]);
        }

        if (!$company) {
            return back()->withInput()->with('error', "ไม่พบลูกค้า \"{$data['company']}\" ในรายชื่อ — เลือกจากรายการ หรือกดรีเฟรชรายชื่อ");
        }

        $name = trim($data['name'] ?? '') ?: $company->name;
        if (ShareGroup::where('name', $name)->exists()) {
            return back()->withInput()->with('error', "มีลิงก์ชื่อ \"{$name}\" อยู่แล้ว — ตั้งชื่อใหม่ เช่น เพิ่มวันที่ต่อท้าย");
        }

        $group = ShareGroup::create(['name' => $name, 'company_folder_id' => $company->folder_id, 'company_name' => $company->name]);

        return redirect()->route('workdrive.show', $group)->with('ok', 'สร้างแล้ว — ติ๊กเลือกรายการจากโฟลเดอร์ลูกค้าได้เลย');
    }

    public function refreshCompanies()
    {
        RefreshCompanies::dispatch();

        return back()->with('ok', 'เข้าคิวอ่านรายชื่อลูกค้าแล้ว ใช้เวลาประมาณ 5 นาที');
    }

    public function show(Request $request, ShareGroup $group, ShareBrowser $browser)
    {
        $picksByCategory = $group->picks()->get()->groupBy('category');
        $pickedIds = $group->picks()->pluck('source_id')->flip();
        $companies = WdCompany::orderBy('name')->pluck('name');
        $publicUrl = $group->publicUrl();
        $logs = $group->accessLogs()->limit(30)->get();

        // ติ๊กเลือกจากโฟลเดอร์ลูกค้า (ดึงสดจาก WorkDrive) — เข้าโฟลเดอร์ย่อยได้ด้วย ?browse=
        $browseId = $request->query('browse', $group->company_folder_id);
        $browseItems = [];
        $browseError = null;
        if ($browseId) {
            try {
                $browseItems = $browser->listing($browseId);
            } catch (Throwable $e) {
                $browseError = $e->getMessage();
            }
        }
        $browseName = $browseId && $browseId !== $group->company_folder_id
            ? rescue(fn () => $browser->info($browseId)['name'], $browseId, false) : null;

        return view('workdrive.show', compact(
            'group', 'picksByCategory', 'pickedIds', 'companies', 'publicUrl', 'logs',
            'browseId', 'browseItems', 'browseError', 'browseName'
        ));
    }

    /** ลบออกจากระบบ (ลิงก์ลูกค้าใช้ไม่ได้ทันที) — ไม่แตะอะไรใน WorkDrive */
    public function destroy(ShareGroup $group)
    {
        $group->grants()->delete(); // ข้อมูลจากระบบแชร์สิทธิ์เดิม (ไม่ใช้แล้ว)
        $group->delete();

        return redirect()->route('workdrive.index')->with('ok', "ลบ \"{$group->name}\" แล้ว ลิงก์ลูกค้าใช้ไม่ได้แล้ว");
    }

    public function history(ShareGroup $group)
    {
        $logs = $group->accessLogs()->paginate(50);

        return view('workdrive.history', compact('group', 'logs'));
    }

    // ------------------------------------------------------------ ลิงก์ลูกค้า
    public function updateLink(Request $request, ShareGroup $group)
    {
        $data = $request->validate([
            'password' => 'nullable|string|min:4|max:100',
            'clear_password' => 'nullable|boolean',
            'expires_at' => 'nullable|date',
            'enabled' => 'nullable|boolean',
        ]);

        $update = [
            'link_enabled' => $request->boolean('enabled'),
            'link_expires_at' => !empty($data['expires_at']) ? \Illuminate\Support\Carbon::parse($data['expires_at'])->endOfDay() : null,
        ];
        if ($request->boolean('clear_password')) {
            $update['link_password'] = null;
        } elseif (!empty($data['password'])) {
            $update['link_password'] = $data['password']; // cast 'hashed' เข้ารหัสให้
        }
        $group->update($update);

        return back()->with('ok', 'บันทึกการตั้งค่าลิงก์แล้ว');
    }

    /** สร้างลิงก์ใหม่ — ลิงก์เก่าใช้ไม่ได้ทันที */
    public function regeneratePublicLink(ShareGroup $group)
    {
        $group->regeneratePublicToken();

        return back()->with('ok', 'สร้างลิงก์ใหม่แล้ว ลิงก์เดิมใช้ไม่ได้แล้ว');
    }

    // ------------------------------------------------------------ รายการที่ pick
    /** ติ๊กเลือกจากรายการในโฟลเดอร์ลูกค้า */
    public function selectPicks(Request $request, ShareGroup $group, ShareBrowser $browser)
    {
        $data = $request->validate([
            'browse' => 'required|string|max:64',
            'ids' => 'required|array|max:500',
            'ids.*' => 'string|max:64',
            'category' => 'nullable|string|max:200',
        ]);

        // ใช้ข้อมูลจากรายการในโฟลเดอร์นั้นเท่านั้น (กันการส่ง id อื่นเข้ามา)
        $listing = collect($browser->listing($data['browse']))->keyBy('id');
        $added = 0;
        foreach ($data['ids'] as $id) {
            if (!$item = $listing->get($id)) {
                continue;
            }
            $pick = SharePick::firstOrCreate(
                ['share_group_id' => $group->id, 'category' => trim($data['category'] ?? ''), 'source_id' => $id],
                ['source' => $item['name'], 'source_name' => $item['name'], 'source_type' => $item['is_folder'] ? 'folder' : 'file', 'status' => 'ok']
            );
            $added += $pick->wasRecentlyCreated ? 1 : 0;
        }

        return back()->with('ok', "เพิ่ม {$added} รายการ");
    }

    public function import(Request $request, ShareGroup $group, PickImporter $importer)
    {
        $request->validate([
            'company' => 'required|string|max:255',
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'replace' => 'nullable|boolean',
        ]);

        $company = $this->pickCompany($group, $request->input('company'));
        if (!$company) {
            return back()->withInput()->with('error', 'เลือกลูกค้าจากรายการก่อน');
        }

        $r = $importer->import($group, $request->file('file')->getRealPath(), $request->boolean('replace'), $company->folder_id);

        return back()->with('ok', "เพิ่ม {$r['added']} รายการ, ซ้ำ {$r['skipped']} รายการ")->with('invalid', $r['invalid']);
    }

    public function template()
    {
        $writer = new Xlsx(PickImporter::template());

        return response()->streamDownload(fn () => $writer->save('php://output'), 'workdrive-picks-template.xlsx');
    }

    /** วางชื่อโฟลเดอร์ได้หลายบรรทัด */
    public function addPick(Request $request, ShareGroup $group, PickImporter $importer)
    {
        $data = $request->validate([
            'company' => 'required|string|max:255',
            'category' => 'nullable|string|max:200',
            'sources' => 'required|string|max:50000',
        ]);

        $company = $this->pickCompany($group, $data['company']);
        if (!$company) {
            return back()->withInput()->with('error', "ไม่พบลูกค้า \"{$data['company']}\" — เลือกจากรายการ");
        }

        $r = $importer->importText($group, $data['category'] ?? '', $data['sources'], $company->folder_id);

        return back()->withInput($r['invalid'] ? $request->only('category') : [])
            ->with($r['added'] || $r['skipped'] ? 'ok' : 'error', "เพิ่ม {$r['added']} รายการ, ซ้ำ {$r['skipped']}, ไม่พบ " . count($r['invalid']))
            ->with('invalid', $r['invalid']);
    }

    public function removePick(ShareGroup $group, SharePick $pick)
    {
        abort_unless($pick->share_group_id === $group->id, 404);
        $pick->delete();

        return back()->with('ok', 'เอารายการออกแล้ว ลูกค้าจะไม่เห็นรายการนี้ทันที');
    }

    /** หาลูกค้าที่เลือก — ถ้ายังไม่ผูก ให้ผูกกับลูกค้านี้ */
    private function pickCompany(ShareGroup $group, ?string $name): ?WdCompany
    {
        $company = WdCompany::where('name', trim((string) $name))->first();
        if ($company && !$group->company_folder_id) {
            $group->update(['company_folder_id' => $company->folder_id, 'company_name' => $company->name]);
        }

        return $company;
    }
}
