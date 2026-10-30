# WorkDrive Share (Laravel)

ระบบนี้ใช้ pick ไฟล์หรือโฟลเดอร์จากที่ไหนก็ได้ใน Zoho WorkDrive โดยไม่ย้ายต้นฉบับ จัดเป็นโฟลเดอร์ตามบริษัทและหมวด แล้วแชร์ให้บุคคลภายนอกได้โดยตรง ผู้ใช้เข้าระบบด้วยบัญชี Zoho

```
ต้นฉบับ (อยู่ที่เดิม)                 แชร์ภายนอก/                ← WORKDRIVE_SHARE_ROOT_ID
  Team Folder X/สัญญา1.pdf   ──▶       บริษัท A/              ← แชร์ให้ลูกค้า A
  Team Folder Y/งบ/          ──▶         สัญญา/สัญญา1.pdf
                                         บัญชี/งบ/...
```

## หน้าจอ

| หน้า | ทำอะไรได้ |
|---|---|
| `/workdrive` | สร้างโฟลเดอร์แชร์ใหม่ (เช่น "บริษัท A") ระบบจะสร้างโฟลเดอร์ใน WorkDrive ให้ และแสดงรายการโฟลเดอร์ทั้งหมด |
| `/workdrive/{id}` | อัปโหลด Excel เพื่อ pick, เพิ่มทีละรายการ, ซิงก์ตอนนี้, แชร์ให้อีเมล, สร้างลิงก์ (รหัสผ่าน/วันหมดอายุ) |
| `/workdrive/template` | ดาวน์โหลด Excel ตัวอย่าง |
| `/auth/zoho` | Login ด้วยบัญชี Zoho (ลิงก์นี้นำไปแปะใน Zoho ได้) |

**รูปแบบไฟล์ Excel** (แถวแรกเป็นหัวตาราง)

| A: หมวด | B: ลิงก์หรือ ID | C: หมายเหตุ |
|---|---|---|
| สัญญา | https://workdrive.zoho.com/file/xxxx | ไฟล์เดียว |
| บัญชี | https://workdrive.zoho.com/folder/yyyy | ทั้งโฟลเดอร์ |

## ติดตั้งในโปรเจกต์ Laravel 10/11/12

1. ติดตั้ง package ที่ใช้อ่านและเขียน Excel
   ```
   composer require phpoffice/phpspreadsheet
   ```
2. copy โฟลเดอร์ `app/`, `config/`, `database/`, `resources/`, `routes/` ไปวางทับในโปรเจกต์
3. เพิ่มบรรทัดนี้ท้ายไฟล์ `routes/web.php`
   ```php
   require __DIR__.'/workdrive.php';
   ```
   ถ้าโปรเจกต์มี route ชื่อ `login` หรือ `logout` อยู่แล้ว เช่น จาก Breeze ให้ลบสองบรรทัดนั้นใน `routes/workdrive.php` ออก
4. เพิ่ม scheduler ให้ซิงก์ทุกชั่วโมง ในไฟล์ `routes/console.php` (Laravel 11 ขึ้นไป)
   ```php
   use Illuminate\Support\Facades\Schedule;
   Schedule::command('workdrive:sync')->hourly()->withoutOverlapping();
   ```
   แล้วตั้ง cron บนเซิร์ฟเวอร์ (ใน Plesk ใช้ Scheduled Tasks ได้)
   ```
   * * * * * cd /path/project && php artisan schedule:run >> /dev/null 2>&1
   ```
5. รัน queue worker สำหรับปุ่ม "ซิงก์ตอนนี้"
   ```
   php artisan queue:work
   ```
   ถ้าไม่อยากรัน worker ให้ตั้ง `QUEUE_CONNECTION=sync` แทน (ระบบจะซิงก์ทันทีระหว่างที่หน้าเว็บรอ)
6. สร้างตารางในฐานข้อมูล
   ```
   php artisan migrate
   ```

## ตั้งค่า Zoho (api-console.zoho.com)

ต้องสร้าง Client 2 ตัว

**A) Self Client** สำหรับให้ระบบซิงก์ไฟล์
- Scope: `WorkDrive.files.ALL,WorkDrive.links.ALL,WorkDrive.team.READ`
- นำ Grant code ที่ได้ไปแลกเป็น refresh token
  ```
  curl -X POST "https://accounts.zoho.com/oauth/v2/token?grant_type=authorization_code&client_id=...&client_secret=...&code=GRANT_CODE"
  ```
- ใช้บัญชีที่มีสิทธิ์ในโฟลเดอร์ต้นฉบับ และใน Team Folder ปลายทาง

**B) Server-based Application** สำหรับ Login
- Homepage URL: `https://โดเมนของคุณ`
- Authorized Redirect URI: `https://โดเมนของคุณ/auth/zoho/callback`

**ค่าที่ต้องใส่ใน `.env`**
```
WORKDRIVE_DC=com
WORKDRIVE_CLIENT_ID=1000.xxx          # จาก A
WORKDRIVE_CLIENT_SECRET=xxx
WORKDRIVE_REFRESH_TOKEN=1000.xxx.xxx
WORKDRIVE_SHARE_ROOT_ID=xxxxxxxx      # ID ของโฟลเดอร์ "แชร์ภายนอก"

ZOHO_LOGIN_CLIENT_ID=1000.yyy         # จาก B
ZOHO_LOGIN_CLIENT_SECRET=yyy
ZOHO_LOGIN_ALLOWED_DOMAINS=thefirstgoodmangroup.com
```

สุดท้าย เข้า Admin Console ของ WorkDrive แล้วเปิด **External Sharing**

## การ Login ด้วย Zoho

- นำลิงก์ `https://โดเมนของคุณ/auth/zoho` ไปแปะใน Zoho ได้ เช่น Web Tab ใน Zoho CRM หรือ bookmark
- ถ้าผู้ใช้ล็อกอิน Zoho อยู่แล้ว ครั้งแรกจะเห็นหน้าขออนุญาต (Accept) หลังจากนั้นกดลิงก์ครั้งเดียวก็เข้าระบบได้เลย
- รับเฉพาะอีเมลในโดเมนที่ตั้งไว้ใน `ZOHO_LOGIN_ALLOWED_DOMAINS` และระบบจะสร้างผู้ใช้ให้อัตโนมัติ
- ควรตั้งให้ลิงก์เปิดในแท็บใหม่ เพราะหน้า login ของ Zoho อาจไม่ยอมให้แสดงใน iframe

## การทำงานของการซิงก์

ทุกรอบ ระบบจะทำงานดังนี้
- อ่านรายการที่ pick ไว้ ถ้าเป็นโฟลเดอร์จะไล่ดึงไฟล์ข้างในทั้งหมด
- เทียบกับตาราง `share_copies` แล้วทำตามกรณี
  - ไฟล์ใหม่ → คัดลอกไปโฟลเดอร์แชร์ (copy บนเซิร์ฟเวอร์ Zoho ไม่ดาวน์โหลดไฟล์ลงเครื่อง)
  - ต้นฉบับถูกแก้ → ย้ายสำเนาเก่าลงถังขยะ แล้วคัดลอกใหม่
  - เอาออกจากรายการแล้ว → ย้ายสำเนาลงถังขยะ
- มีกลไกกันความผิดพลาด
  - ถ้ามีรายการใดเปิดต้นฉบับไม่ได้ ระบบจะงดลบสำเนาในรอบนั้น เพื่อกันการลบผิด
  - ใช้ lock กันการซิงก์ซ้อนกันในโฟลเดอร์เดียวกัน

## สั่งงานผ่าน command line

```
php artisan workdrive:sync               # ซิงก์ทุกโฟลเดอร์
php artisan workdrive:sync "บริษัท A"    # ซิงก์เฉพาะโฟลเดอร์ที่ระบุ
```

## หมายเหตุ

- ไม่ควรแก้ไฟล์ในโฟลเดอร์แชร์โดยตรง ให้แก้ที่ต้นฉบับเสมอ
- `role_id` 34 คือสิทธิ์ View ถ้า API ตอบ error เรื่อง role ให้ตรวจค่านี้ในเอกสาร Zoho WorkDrive API
- ทดลองกับ 1 บริษัทและไฟล์ไม่กี่ไฟล์ก่อนใช้งานจริง
