<?php

namespace App\Services\WorkDrive;

use App\Models\ShareGroup;
use App\Models\SharePick;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Throwable;

/**
 * เพิ่มรายการ pick จากข้อความหลายบรรทัด หรือจากไฟล์ Excel / CSV
 * Excel: คอลัมน์ A = หมวด, B = ชื่อโฟลเดอร์ / path / ลิงก์ / ID, C = หมายเหตุ (ไม่บังคับ)
 */
class PickImporter
{
    public function __construct(private FolderResolver $resolver)
    {
    }

    /**
     * @param array<int, array{0:string,1:string,2:string}> $rows [หมวด, ข้อความ, ป้ายบอกตำแหน่ง]
     * @return array{added:int,skipped:int,invalid:array<int,string>}
     */
    public function addRows(ShareGroup $group, array $rows, ?string $companyFolderId = null): array
    {
        $result = ['added' => 0, 'skipped' => 0, 'invalid' => []];
        $companyFolderId ??= $group->company_folder_id;

        foreach ($rows as [$category, $source, $label]) {
            try {
                $found = $this->resolver->resolve($source, $companyFolderId);
            } catch (Throwable $e) {
                $result['invalid'][] = "{$label}: {$source} — {$e->getMessage()}";
                continue;
            }

            $pick = SharePick::firstOrCreate(
                ['share_group_id' => $group->id, 'category' => $category, 'source_id' => $found['id']],
                [
                    'source' => $source,
                    'source_name' => $found['name'],
                    'source_type' => $found['is_folder'] ? 'folder' : 'file',
                    'permalink' => $found['permalink'],
                    'status' => 'pending',
                ]
            );
            $pick->wasRecentlyCreated ? $result['added']++ : $result['skipped']++;
        }

        return $result;
    }

    /** ข้อความหลายบรรทัด (บรรทัดละ 1 รายการ) ภายใต้หมวดเดียว */
    public function importText(ShareGroup $group, string $category, string $text, ?string $companyFolderId = null): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $text)), 'strlen'));

        return $this->addRows($group, array_map(fn ($l, $i) => [trim($category), $l, 'บรรทัด ' . ($i + 1)], $lines, array_keys($lines)), $companyFolderId);
    }

    public function import(ShareGroup $group, string $path, bool $replace = false, ?string $companyFolderId = null): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);
        $rows = [];
        $invalid = [];

        foreach ($sheet as $i => $row) {
            $category = trim((string) ($row[0] ?? ''));
            $source = trim((string) ($row[1] ?? ''));
            if ($i === 0 || ($category === '' && $source === '')) {
                continue; // แถวแรกเป็นหัวตาราง
            }
            if ($source === '') {
                $invalid[] = 'แถว ' . ($i + 1) . ': ไม่มีชื่อโฟลเดอร์';
                continue;
            }
            $rows[] = [$category, $source, 'แถว ' . ($i + 1)];
        }

        if ($replace) {
            $group->picks()->delete();
        }

        $result = $this->addRows($group, $rows, $companyFolderId);
        $result['invalid'] = array_merge($invalid, $result['invalid']);

        return $result;
    }

    /** สร้างไฟล์ Excel ตัวอย่างให้ดาวน์โหลด */
    public static function template(): Spreadsheet
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('picks');
        $sheet->fromArray([
            ['หมวด', 'ชื่อโฟลเดอร์ / path / ลิงก์ WorkDrive', 'หมายเหตุ'],
            ['เอกสารแรงงาน', 'MJ465023', 'ชื่อโฟลเดอร์ในบริษัทของกลุ่ม'],
            ['เอกสารแรงงาน', 'MJ465023\\สัญญาจ้าง.pdf', 'path ข้างในโฟลเดอร์'],
            ['สัญญา', 'https://workdrive.zoho.com/file/xxxxxxxxxxxxxxxxxxxxxxxxx', 'ลิงก์ตรง'],
        ]);
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        foreach (['A' => 18, 'B' => 70, 'C' => 30] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        return $book;
    }
}
