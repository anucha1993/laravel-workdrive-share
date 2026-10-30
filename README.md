# WorkDrive Share (Laravel 12)

หน้าเบราว์เซอร์ไฟล์บนโดเมนของเรา สำหรับส่งเอกสารใน Zoho WorkDrive ให้ลูกค้า (Account)
**1 ลิงก์ต่อ 1 ลูกค้า** — ลูกค้าเห็นเฉพาะรายการที่ pick ไว้ เปิดดู/ดาวน์โหลดผ่านเซิร์ฟเวอร์เรา
ไม่ copy ไม่ sync ไม่แชร์สิทธิ์ใน WorkDrive และไม่แก้ ย้าย หรือลบต้นฉบับ (ใช้ API อ่านอย่างเดียว)

- ระบบ: https://www.workdrive.trackingmouthefirst.com (Login ด้วย Zoho เฉพาะ @thefirstgoodmangroup.com)
- ลิงก์ลูกค้า: `https://www.workdrive.trackingmouthefirst.com/s/{token}`

## ฝั่งพนักงาน

1. สร้างลิงก์ลูกค้า: เลือกลูกค้าจากโฟลเดอร์ใต้ `Zoho CRM - Workdrive / Accounts` (หรือวางลิงก์โฟลเดอร์ลูกค้าใหม่)
2. Pick รายการ 3 วิธี
   - ติ๊กเลือกจากโฟลเดอร์ลูกค้า (ดึงสด กดเข้าโฟลเดอร์ย่อยได้)
   - วางชื่อโฟลเดอร์หลายบรรทัด เช่น `MH468133`
   - Excel (A = หมวด ไม่บังคับ, B = ชื่อโฟลเดอร์)
3. ตั้งค่าลิงก์: รหัสผ่าน, วันหมดอายุ, เปิด/ปิด, สร้างลิงก์ใหม่ (เพิกถอนลิงก์เดิม)
4. ดู log การเข้าดู/ดาวน์โหลดของลูกค้า

## ฝั่งลูกค้า (`/s/{token}`)

- หน้าตาแบบ WorkDrive: breadcrumb (ชื่อลูกค้าเป็นโฟลเดอร์บนสุด), Name, Last Modified, Size
- เห็นเฉพาะรายการที่ pick (Associated Contacts / Deals และอื่นๆ ถูกซ่อน) กดเข้าโฟลเดอร์ย่อยได้ (ดึงสดจาก API)
- ดาวน์โหลดรายไฟล์ และ "ดาวน์โหลดทั้งหมด" เป็น ZIP (stream ทีละไฟล์ ไม่เก็บบนเซิร์ฟเวอร์)
- ไฟล์ Zoho Writer/Sheet/Show แปลงเป็น docx/xlsx/pptx ตอนดาวน์โหลด
- ซ่อนไฟล์ระบบ (Thumbs.db, desktop.ini, .DS_Store)

## ความปลอดภัย

- ทุก request ตรวจว่า resource อยู่ใต้รายการที่ pick โดยไล่ `parent_id` ขึ้นไป — ไม่อยู่ = 404 และบันทึก log `denied`
- token ลิงก์สุ่ม 40 ตัว, รหัสผ่าน (bcrypt), วันหมดอายุ, ปิด/เพิกถอนได้, throttle, `noindex`, `no-referrer`
- token ของ Zoho อยู่ฝั่งเซิร์ฟเวอร์เท่านั้น ไฟล์ส่งผ่านเซิร์ฟเวอร์เรา
- บันทึกทุกการเปิด/ดาวน์โหลด/ใส่รหัสผ่าน (ตาราง `share_access_logs`) พร้อม IP

## ตั้งค่า Zoho (api-console.zoho.com, DC .com)

**A) Self Client** (อ่านอย่างเดียว)
```
WorkDrive.files.READ,ZohoFiles.files.READ
```
แลก Grant code เป็น refresh token (ทำในเครื่องตัวเอง ห้ามแปะ secret ลงแชต/โค้ด):
```
curl.exe -X POST https://accounts.zoho.com/oauth/v2/token -d grant_type=authorization_code -d client_id=... -d client_secret=... -d code=GRANT_CODE
```

**B) Server-based Application** (Login พนักงาน, OpenID Connect)
- Homepage URL: `https://www.workdrive.trackingmouthefirst.com`
- Redirect URI: `https://www.workdrive.trackingmouthefirst.com/auth/zoho/callback`

## `.env`

| ค่า | ความหมาย |
|---|---|
| `WORKDRIVE_CLIENT_ID/SECRET/REFRESH_TOKEN` | Self Client (A) |
| `WORKDRIVE_COMPANY_ROOT_ID` | ID โฟลเดอร์ Accounts = `cwc2r5999776637444d39802906b76cf9f27c` |
| `WORKDRIVE_REQUEST_DELAY_MS` | หน่วงเวลาระหว่างคำขอ API (ค่าเริ่มต้น 700) |
| `ZOHO_LOGIN_CLIENT_ID/SECRET` | Server-based Application (B) |

## ติดตั้งบน Plesk

1. PHP 8.2+ · Document root = `<โปรเจกต์>/public` · Let's Encrypt (รวม www) · Preferred domain = www
2. `git pull` แล้วรัน
   ```
   composer install --no-dev --optimize-autoloader
   php artisan migrate --force
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
   (`public/build` อยู่ใน git แล้ว ไม่ต้องมี Node บนเซิร์ฟเวอร์)
3. Scheduled Task ทุกนาที (อัปเดตรายชื่อลูกค้าทุกคืน + คิว)
   ```
   cd /var/www/vhosts/trackingmouthefirst.com/workdrive.trackingmouthefirst.com && /opt/plesk/php/8.4/bin/php artisan schedule:run
   ```
4. ไฟล์ ZIP ใหญ่: ให้ PHP-FPM / nginx timeout พอ (โค้ดตั้ง `set_time_limit(0)` และส่ง `X-Accel-Buffering: no` แล้ว)

## คำสั่ง

```
php artisan workdrive:check <ลิงก์หรือ ID>   # ทดสอบการเชื่อมต่อ (อ่านอย่างเดียว)
php artisan workdrive:companies               # อ่านรายชื่อลูกค้าใต้ Accounts (~5 นาที)
php artisan test                              # Feature test (mock Zoho API ด้วย Http::fake)
```
