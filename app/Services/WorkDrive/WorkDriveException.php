<?php

namespace App\Services\WorkDrive;

use RuntimeException;

/** error จาก WorkDrive API พร้อมรหัส error ของ Zoho (เช่น R910 = ไม่อนุญาตแชร์ให้อีเมลภายนอก) */
class WorkDriveException extends RuntimeException
{
    /** คำอธิบายภาษาไทยของ error ที่พบบ่อยตามเอกสาร POST /permissions */
    private const HINTS = [
        'R008' => 'ไม่พบไฟล์/โฟลเดอร์ หรือบัญชีระบบไม่มีสิทธิ์เข้าถึง',
        'R012' => 'ไฟล์/โฟลเดอร์ไม่อยู่ในสถานะปกติ (อาจอยู่ในถังขยะ)',
        'R905' => 'ไม่อนุญาตให้แชร์ภายนอก',
        'R910' => 'แอดมินไม่อนุญาตให้แชร์ให้อีเมลภายนอก',
        'R914' => 'แชร์ให้อีเมลภายนอกได้เฉพาะโดเมนที่อยู่ใน whitelist',
        'R952' => 'ไม่ได้เปิดสิทธิ์แชร์ให้ผู้ใช้นอกองค์กร',
        'R953' => 'ไม่ได้เปิดสิทธิ์แชร์ให้ผู้ที่ไม่มีบัญชี Zoho',
        'R955' => 'จำกัดการแชร์ทรัพยากรนี้ออกนอกองค์กร',
        'R214' => 'ไม่รู้จักอีเมลผู้รับ',
    ];

    public ?string $zohoCode;

    public function __construct(public string $method, public string $path, public int $status, array $json, string $body)
    {
        $this->zohoCode = $json['errors'][0]['id'] ?? null;
        $title = $json['errors'][0]['title'] ?? mb_substr($body, 0, 300);
        $hint = self::HINTS[$this->zohoCode] ?? null;

        parent::__construct(trim(($hint ? "{$hint} — " : '') . "{$this->zohoCode} {$title} ({$method} {$path} → {$status})"));
    }
}
