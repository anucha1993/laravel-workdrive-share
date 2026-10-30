<?php

namespace App\Services\WorkDrive;

use App\Models\ShareGroup;
use Generator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * อ่านโครงสร้างไฟล์สำหรับลิงก์ลูกค้า (อ่านอย่างเดียว)
 * ทุกรายการที่เปิดได้ต้องเป็นรายการที่ pick หรืออยู่ใต้รายการที่ pick — ตรวจโดยไล่ parent_id ขึ้นไป
 */
class ShareBrowser
{
    /** ไฟล์ระบบที่ไม่ต้องแสดงให้ลูกค้า */
    private const HIDDEN = ['thumbs.db', 'desktop.ini', '.ds_store'];

    /** ไฟล์ของ Zoho (Writer / Sheet / Show) → นามสกุลที่แปลงตอนดาวน์โหลด */
    private const NATIVE = [
        'writer' => 'docx', 'zohowriter' => 'docx', 'document' => 'docx',
        'spreadsheet' => 'xlsx', 'zohosheet' => 'xlsx', 'sheet' => 'xlsx',
        'presentation' => 'pptx', 'zohoshow' => 'pptx', 'show' => 'pptx',
    ];

    private const MAX_DEPTH = 30;

    public function __construct(private WorkDriveClient $wd)
    {
    }

    /** @return array<string, \App\Models\SharePick> source_id => pick */
    public function picks(ShareGroup $group): array
    {
        return $group->picks()->get()->keyBy('source_id')->all();
    }

    /** ข้อมูลรายการ (cache สั้นๆ เพื่อไม่ยิง API ซ้ำทุกคลิก) */
    public function info(string $id): array
    {
        return Cache::remember("wd-info-{$id}", 300, fn () => WorkDriveClient::summary($this->wd->info($id)));
    }

    /**
     * ตรวจว่า $id อยู่ใต้รายการที่ pick หรือไม่
     * @return array<int, array>|null breadcrumb จากรายการที่ pick ลงมาถึง $id (null = ไม่มีสิทธิ์)
     */
    public function locate(ShareGroup $group, string $id): ?array
    {
        $picks = $this->picks($group);
        $chain = [];
        $current = $id;

        for ($i = 0; $i < self::MAX_DEPTH; $i++) {
            $item = $this->info($current);
            array_unshift($chain, $item);
            if (isset($picks[$current])) {
                return $chain;
            }
            $parent = $item['parent_id'];
            if (!$parent || $parent === config('workdrive.company_root_id')) {
                return null;
            }
            $current = $parent;
        }

        return null;
    }

    /** รายการในโฟลเดอร์ เรียงโฟลเดอร์ก่อน แล้วตามชื่อ */
    public function listing(string $folderId): array
    {
        $items = Cache::remember("wd-list-{$folderId}", 60, fn () => $this->wd->children($folderId));
        $items = array_values(array_filter($items, fn ($i) => !in_array(mb_strtolower($i['name']), self::HIDDEN, true)
            && !str_starts_with($i['name'], '~$')));

        usort($items, fn ($a, $b) => [$b['is_folder'], mb_strtolower($a['name'])] <=> [$a['is_folder'], mb_strtolower($b['name'])]);

        return $items;
    }

    /** ระดับบนสุดของลิงก์ลูกค้า: รายการที่ pick แยกตามหมวด (หมวดว่าง = แสดงตรงนั้นเลย) */
    public function rootItems(ShareGroup $group): Collection
    {
        return $group->picks()->get()->map(function ($pick) {
            $item = rescue(fn () => $this->info($pick->source_id), null, false);

            return $item ? $item + ['category' => $pick->category] : null;
        })->filter()->groupBy('category');
    }

    /** ชื่อไฟล์และ format สำหรับดาวน์โหลด (แปลงไฟล์ Zoho เป็น Office) */
    public function downloadName(array $item): array
    {
        $target = self::NATIVE[mb_strtolower((string) $item['type'])] ?? null;
        if ($target && mb_strtolower((string) $item['extn']) !== $target) {
            return [preg_replace('/\.(zdoc|zsheet|zshow)$/i', '', $item['name']) . ".{$target}", $target];
        }

        return [$item['name'], null];
    }

    /** ไล่ไฟล์ทั้งหมดใต้โฟลเดอร์ (สำหรับ ZIP) @return Generator<array{0:array,1:string}> [item, path ใน zip] */
    public function walk(string $folderId, string $prefix, int $depth = 0): Generator
    {
        if ($depth > self::MAX_DEPTH) {
            return;
        }
        foreach ($this->listing($folderId) as $item) {
            if ($item['is_folder']) {
                yield from $this->walk($item['id'], $prefix . $this->safe($item['name']) . '/', $depth + 1);
            } else {
                yield [$item, $prefix . $this->safe($this->downloadName($item)[0])];
            }
        }
    }

    public function safe(string $name): string
    {
        return trim(str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $name)) ?: '_';
    }
}
