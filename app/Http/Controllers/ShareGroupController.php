<?php

namespace App\Http\Controllers;

use App\Jobs\ApplyShareGroup;
use App\Jobs\SendShareSummary;
use App\Models\ShareGroup;
use App\Models\SharePick;
use App\Models\ShareRecipient;
use App\Services\WorkDrive\PickImporter;
use App\Services\WorkDrive\WorkDriveClient;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

class ShareGroupController extends Controller
{
    public function index()
    {
        $groups = ShareGroup::withCount(['picks', 'recipients', 'grants' => fn ($q) => $q->where('status', 'granted')])
            ->orderBy('name')->get();

        return view('workdrive.index', compact('groups'));
    }

    /** สร้างกลุ่มแชร์ใหม่ (เช่น ชื่อบริษัท) — ไม่สร้างอะไรใน WorkDrive */
    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:200|unique:share_groups,name']);
        $group = ShareGroup::create($data);

        return redirect()->route('workdrive.show', $group)->with('ok', 'สร้างกลุ่มแชร์แล้ว — เพิ่มรายการและอีเมลผู้รับได้เลย');
    }

    public function show(ShareGroup $group)
    {
        $picksByCategory = $group->picks()->get()->groupBy('category');
        $recipients = $group->recipients()->get();
        $grants = $group->grants()->get();
        $runs = $group->runs()->limit(5)->get();

        // สถานะต่อรายการ / ต่ออีเมล
        $grantedByResource = $grants->where('status', 'granted')->countBy('resource_id');
        $grantedByEmail = $grants->where('status', 'granted')->countBy('email');
        $errorGrants = $grants->where('status', 'error');
        $pending = $this->pendingCount($group, $grants);

        return view('workdrive.show', compact(
            'group', 'picksByCategory', 'recipients', 'runs', 'grantedByResource', 'grantedByEmail', 'errorGrants', 'pending'
        ));
    }

    /** ลบกลุ่ม: ถอนสิทธิ์ทั้งหมดก่อน แล้วค่อยลบ (ทำในคิว) */
    public function destroy(ShareGroup $group)
    {
        $group->picks()->delete();
        $group->recipients()->delete();
        ApplyShareGroup::dispatch($group, 'delete', false, true);

        return redirect()->route('workdrive.index')->with('ok', "กำลังถอนสิทธิ์ทั้งหมดของ \"{$group->name}\" แล้วจะลบกลุ่มเมื่อเสร็จ");
    }

    public function history(ShareGroup $group)
    {
        $runs = $group->runs()->paginate(30);

        return view('workdrive.history', compact('group', 'runs'));
    }

    // ------------------------------------------------------------ รายการที่ pick
    public function import(Request $request, ShareGroup $group, PickImporter $importer)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'replace' => 'nullable|boolean',
        ]);

        $r = $importer->import($group, $request->file('file')->getRealPath(), $request->boolean('replace'));

        return back()->with('ok', "เพิ่ม {$r['added']} รายการ, ซ้ำ {$r['skipped']} รายการ — กด \"แชร์ / อัปเดตสิทธิ์\" เพื่อให้มีผล")
            ->with('invalid', $r['invalid']);
    }

    public function template()
    {
        $writer = new Xlsx(PickImporter::template());

        return response()->streamDownload(fn () => $writer->save('php://output'), 'workdrive-picks-template.xlsx');
    }

    public function addPick(Request $request, ShareGroup $group)
    {
        $data = $request->validate(['category' => 'required|string|max:200', 'source' => 'required|string']);

        try {
            $id = WorkDriveClient::extractId($data['source']);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        SharePick::firstOrCreate(
            ['share_group_id' => $group->id, 'category' => trim($data['category']), 'source_id' => $id],
            ['source' => $data['source'], 'status' => 'pending']
        );

        return back()->with('ok', 'เพิ่มรายการแล้ว — กด "แชร์ / อัปเดตสิทธิ์" เพื่อให้มีผล');
    }

    public function removePick(ShareGroup $group, SharePick $pick)
    {
        abort_unless($pick->share_group_id === $group->id, 404);
        $pick->delete();

        return back()->with('ok', 'เอารายการออกแล้ว — กด "แชร์ / อัปเดตสิทธิ์" เพื่อถอนสิทธิ์');
    }

    // ------------------------------------------------------------ ผู้รับ
    /** เพิ่มอีเมลได้หลายรายการ คั่นด้วยขึ้นบรรทัดใหม่ , หรือ ; */
    public function addRecipients(Request $request, ShareGroup $group)
    {
        $request->validate(['emails' => 'required|string|max:5000']);

        $emails = collect(preg_split('/[\s,;]+/', strtolower($request->input('emails'))))->filter()->unique();
        $invalid = $emails->reject(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
        if ($invalid->isNotEmpty()) {
            return back()->withInput()->with('error', 'อีเมลไม่ถูกต้อง: ' . $invalid->implode(', '));
        }

        $added = 0;
        foreach ($emails as $email) {
            $added += ShareRecipient::firstOrCreate(['share_group_id' => $group->id, 'email' => $email])->wasRecentlyCreated ? 1 : 0;
        }

        return back()->with('ok', "เพิ่มผู้รับ {$added} คน — กด \"แชร์ / อัปเดตสิทธิ์\" เพื่อให้มีผล");
    }

    public function removeRecipient(ShareGroup $group, ShareRecipient $recipient)
    {
        abort_unless($recipient->share_group_id === $group->id, 404);
        $recipient->delete();

        return back()->with('ok', "เอา {$recipient->email} ออกแล้ว — กด \"แชร์ / อัปเดตสิทธิ์\" เพื่อถอนสิทธิ์");
    }

    // ------------------------------------------------------------ แชร์
    public function apply(Request $request, ShareGroup $group)
    {
        ApplyShareGroup::dispatch($group, 'manual', $request->boolean('notify', true));

        return back()->with('ok', 'เข้าคิวแล้ว ระบบจะให้/ถอนสิทธิ์ภายในไม่กี่นาที รีเฟรชหน้านี้เพื่อดูผล');
    }

    public function resendSummary(Request $request, ShareGroup $group)
    {
        $email = $request->input('email');
        SendShareSummary::dispatch($group, $email ? [$email] : null);

        return back()->with('ok', 'เข้าคิวส่งอีเมลสรุปแล้ว');
    }

    /** จำนวนสิทธิ์ที่ยังต้องให้/ถอน (เพื่อเตือนให้กดแชร์) */
    private function pendingCount(ShareGroup $group, $grants): int
    {
        $sourceIds = $group->picks()->distinct()->pluck('source_id');
        $emails = $group->recipients()->pluck('email');
        $desired = $sourceIds->crossJoin($emails)->map(fn ($p) => "{$p[0]}|{$p[1]}");
        $granted = $grants->where('status', 'granted')->map(fn ($g) => "{$g->resource_id}|{$g->email}");

        return $desired->diff($granted)->count() + $granted->diff($desired)->count();
    }
}
