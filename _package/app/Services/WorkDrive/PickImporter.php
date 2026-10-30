<?php

namespace App\Services\WorkDrive;

use App\Models\ShareFolder;
use App\Models\SharePick;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Throwable;

/**
 * นำเข้ารายการ pick จากไฟล์ Excel / CSV
 * คอลัมน์ A = หมวด, B = ลิงก์หรือ ID ของไฟล์/โฟลเดอร์ใน WorkDrive, C = หมายเหตุ (ไม่บังคับ)
 * แถวแรกเป็นหัวตารางได้ (ระบบข้ามแถวที่หา ID ไม่เจอให้อัตโนมัติ)
 */
class PickImporter
{
    /** @return array{added:int,skipped:int,invalid:array<int,string>} */
    public function import(ShareFolder $folder, string $path, bool $replace = false): array
    {
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);
        $result = ['added' => 0, 'skipped' => 0, 'invalid' => []];

        DB::transaction(function () use ($folder, $rows, $replace, &$result) {
            if ($replace) {
                $folder->picks()->delete();
            }

            foreach ($rows as $i => $row) {
                $category = trim((string) ($row[0] ?? ''));
                $source = trim((string) ($row[1] ?? ''));
                if ($category === '' && $source === '') {
                    continue;
                }

                try {
                    $id = WorkDriveClient::extractId($source);
                } catch (Throwable) {
                    if ($i > 0) { // แถวแรกถือเป็นหัวตาราง
                        $result['invalid'][] = 'แถว ' . ($i + 1) . ": {$source}";
                    }
                    continue;
                }

                if ($category === '') {
                    $result['invalid'][] = 'แถว ' . ($i + 1) . ': ไม่มีชื่อหมวด';
                    continue;
                }

                $pick = SharePick::firstOrCreate(
                    ['share_folder_id' => $folder->id, 'category' => $category, 'source_id' => $id],
                    ['source' => $source, 'status' => 'pending']
                );
                $pick->wasRecentlyCreated ? $result['added']++ : $result['skipped']++;
            }
        });

        return $result;
    }

    /** สร้างไฟล์ Excel ตัวอย่างให้ดาวน์โหลด */
    public static function template(): Spreadsheet
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('picks');
        $sheet->fromArray([
            ['หมวด', 'ลิงก์หรือ ID (ไฟล์/โฟลเดอร์ WorkDrive)', 'หมายเหตุ'],
            ['สัญญา', 'https://workdrive.zoho.com/file/xxxxxxxxxxxxxxxxxxxxxxxxx', 'ไฟล์เดียว'],
            ['บัญชี', 'https://workdrive.zoho.com/folder/yyyyyyyyyyyyyyyyyyyyyyyyy', 'ทั้งโฟลเดอร์'],
        ]);
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        foreach (['A' => 18, 'B' => 70, 'C' => 25] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        return $book;
    }
}
