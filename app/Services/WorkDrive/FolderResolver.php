<?php

namespace App\Services\WorkDrive;

use App\Models\WdCompany;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * แปลงสิ่งที่ผู้ใช้วางมาเป็น ID ของไฟล์/โฟลเดอร์ใน WorkDrive (อ่านอย่างเดียว)
 *   - ลิงก์หรือ ID                → ใช้ตรงๆ
 *   - ชื่อ เช่น "MJ465023"         → หาในโฟลเดอร์บริษัทของกลุ่ม
 *   - path เช่น "MJ465023\เอกสาร"  → ไล่ทีละชั้นจากโฟลเดอร์บริษัท
 *   - path เต็ม "\บริษัท...\MJ465023" → ส่วนแรกคือชื่อบริษัท (ใต้ Accounts)
 */
class FolderResolver
{
    public function __construct(private WorkDriveClient $wd)
    {
    }

    /** @return array{id:string,name:string,is_folder:bool,permalink:?string} */
    public function resolve(string $line, ?string $companyFolderId): array
    {
        $line = trim($line);

        if (str_contains($line, 'workdrive.zoho') || preg_match('/^[a-z0-9]{25,}$/', $line)) {
            // อ่านข้อมูลจริง 1 ครั้ง เพื่อได้ชื่อ ชนิด และลิงก์ (และรู้ทันทีถ้าลิงก์ใช้ไม่ได้)
            $item = $this->wd->info(WorkDriveClient::extractId($line));
            $a = $item['attributes'] ?? [];
            return ['id' => $item['id'], 'name' => $a['name'] ?? $item['id'], 'is_folder' => WorkDriveClient::isFolder($item), 'permalink' => $a['permalink'] ?? null];
        }

        $parts = array_values(array_filter(array_map('trim', preg_split('#[\\\\/]+#', $line)), 'strlen'));
        if (!$parts) {
            throw new RuntimeException('ว่าง');
        }

        // path เต็มที่ขึ้นต้นด้วย \ หรือส่วนแรกเป็นชื่อบริษัท → เริ่มจากโฟลเดอร์บริษัทนั้น
        $company = (count($parts) > 1 || !$companyFolderId) ? WdCompany::where('name', $parts[0])->first() : null;
        if ($company) {
            array_shift($parts);
            $current = $company->folder_id;
        } elseif (preg_match('#^[\\\\/]#', $line) || !$companyFolderId) {
            throw new RuntimeException("ไม่พบบริษัท \"{$parts[0]}\" (ลองกดรีเฟรชรายชื่อบริษัท)");
        } else {
            $current = $companyFolderId;
        }

        $name = $company?->name ?? '';
        $isFolder = true;
        foreach ($parts as $part) {
            $child = $this->findChild($current, $part);
            if (!$child) {
                throw new RuntimeException("ไม่พบ \"{$part}\"");
            }
            $current = $child['id'];
            $name = $child['name'];
            $isFolder = $child['is_folder'];
        }

        return ['id' => $current, 'name' => $name, 'is_folder' => $isFolder, 'permalink' => null];
    }

    private function findChild(string $folderId, string $name): ?array
    {
        $children = Cache::remember("wd-children-{$folderId}", 120, fn () => $this->wd->children($folderId));
        $needle = mb_strtolower($name);

        foreach ($children as $c) {
            if (mb_strtolower(trim($c['name'])) === $needle) {
                return $c;
            }
        }

        return null;
    }
}
