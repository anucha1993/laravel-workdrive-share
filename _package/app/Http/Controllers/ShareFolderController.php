<?php

namespace App\Http\Controllers;

use App\Jobs\SyncShareFolder;
use App\Models\ShareFolder;
use App\Models\SharePick;
use App\Services\WorkDrive\PickImporter;
use App\Services\WorkDrive\ShareSyncService;
use App\Services\WorkDrive\WorkDriveClient;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

class ShareFolderController extends Controller
{
    public function index()
    {
        $folders = ShareFolder::withCount(['picks', 'copies'])->orderBy('name')->get();

        return view('workdrive.index', compact('folders'));
    }

    /** สร้างโฟลเดอร์แชร์ใหม่ (ชื่อที่จะ pick ไฟล์เข้าไป) */
    public function store(Request $request, ShareSyncService $sync)
    {
        $data = $request->validate(['name' => 'required|string|max:200|unique:share_folders,name']);
        $folder = ShareFolder::create($data);

        try {
            $sync->ensureFolder($folder);
        } catch (Throwable $e) {
            return redirect()->route('workdrive.show', $folder)
                ->with('error', 'บันทึกแล้ว แต่สร้างโฟลเดอร์ใน WorkDrive ไม่สำเร็จ: ' . $e->getMessage());
        }

        return redirect()->route('workdrive.show', $folder)->with('ok', 'สร้างโฟลเดอร์แล้ว');
    }

    public function show(ShareFolder $folder)
    {
        $folder->load('picks');
        $picksByCategory = $folder->picks->groupBy('category');
        $copyCount = $folder->copies()->count();

        return view('workdrive.show', compact('folder', 'picksByCategory', 'copyCount'));
    }

    public function destroy(ShareFolder $folder)
    {
        $folder->delete(); // ลบเฉพาะในระบบ ไม่ลบโฟลเดอร์ใน WorkDrive
        return redirect()->route('workdrive.index')->with('ok', 'ลบออกจากระบบแล้ว (โฟลเดอร์ใน WorkDrive ยังอยู่)');
    }

    /** อัปโหลด Excel เพื่อ pick หลายรายการพร้อมกัน */
    public function import(Request $request, ShareFolder $folder, PickImporter $importer)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'replace' => 'nullable|boolean',
            'sync_now' => 'nullable|boolean',
        ]);

        $r = $importer->import($folder, $request->file('file')->getRealPath(), $request->boolean('replace'));

        if ($request->boolean('sync_now')) {
            SyncShareFolder::dispatch($folder);
        }

        $msg = "เพิ่ม {$r['added']} รายการ, ซ้ำ {$r['skipped']} รายการ";
        return back()->with('ok', $msg)->with('invalid', $r['invalid']);
    }

    public function template()
    {
        $writer = new Xlsx(PickImporter::template());

        return response()->streamDownload(fn () => $writer->save('php://output'), 'workdrive-picks-template.xlsx');
    }

    /** เพิ่ม pick ทีละรายการ */
    public function addPick(Request $request, ShareFolder $folder)
    {
        $data = $request->validate(['category' => 'required|string|max:200', 'source' => 'required|string']);

        try {
            $id = WorkDriveClient::extractId($data['source']);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        SharePick::firstOrCreate(
            ['share_folder_id' => $folder->id, 'category' => trim($data['category']), 'source_id' => $id],
            ['source' => $data['source'], 'status' => 'pending']
        );

        return back()->with('ok', 'เพิ่มรายการแล้ว — กด "ซิงก์ตอนนี้" หรือรอรอบอัตโนมัติ');
    }

    public function removePick(ShareFolder $folder, SharePick $pick)
    {
        abort_unless($pick->share_folder_id === $folder->id, 404);
        $pick->delete();

        return back()->with('ok', 'เอาออกแล้ว — สำเนาจะถูกลบในรอบซิงก์ถัดไป');
    }

    public function sync(ShareFolder $folder)
    {
        SyncShareFolder::dispatch($folder);

        return back()->with('ok', 'สั่งซิงก์แล้ว รีเฟรชหน้านี้เพื่อดูผล');
    }

    public function shareEmail(Request $request, ShareFolder $folder, WorkDriveClient $wd, ShareSyncService $sync)
    {
        $data = $request->validate(['email' => 'required|email']);

        try {
            $wd->shareEmail($sync->ensureFolder($folder), $data['email'], null, true);
        } catch (Throwable $e) {
            return back()->with('error', 'แชร์ไม่สำเร็จ: ' . $e->getMessage());
        }

        return back()->with('ok', "แชร์ให้ {$data['email']} แล้ว");
    }

    public function createLink(Request $request, ShareFolder $folder, WorkDriveClient $wd, ShareSyncService $sync)
    {
        $data = $request->validate([
            'password' => 'nullable|string|min:4',
            'expire' => 'nullable|date|after:today',
        ]);

        try {
            $link = $wd->createLink($sync->ensureFolder($folder), "แชร์ - {$folder->name}", $data['password'] ?? null, $data['expire'] ?? null);
        } catch (Throwable $e) {
            return back()->with('error', 'สร้างลิงก์ไม่สำเร็จ: ' . $e->getMessage());
        }

        $folder->update(['share_link' => $link]);

        return back()->with('ok', 'สร้างลิงก์แล้ว');
    }
}
