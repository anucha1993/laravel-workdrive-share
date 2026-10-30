# WorkDrive Share (Laravel 12)

แชร์ไฟล์/โฟลเดอร์ใน Zoho WorkDrive ให้บุคคลภายนอก โดย **ให้สิทธิ์ "ดูอย่างเดียว" บนต้นฉบับโดยตรง**
ไม่คัดลอก ไม่ย้าย ไม่แก้ และไม่ลบไฟล์ต้นฉบับ ระบบแตะเฉพาะสิทธิ์แชร์ที่ระบบเป็นผู้ให้เท่านั้น

- URL: https://www.workdrive.trackingmouthefirst.com
- ลิงก์ Login ที่นำไปแปะใน Zoho: https://www.workdrive.trackingmouthefirst.com/auth/zoho

## การใช้งาน

1. สร้าง **กลุ่มแชร์** (เช่น ชื่อบริษัท)
2. เพิ่ม **รายการ** (หมวด + ลิงก์ไฟล์/โฟลเดอร์) ทีละรายการ หรืออัปโหลด Excel (A = หมวด, B = ลิงก์หรือ ID)
3. เพิ่ม **อีเมลผู้รับภายนอก** (ใส่ได้หลายอีเมลพร้อมกัน)
4. กด **แชร์ / อัปเดตสิทธิ์** ระบบเข้าคิวแล้วทำงานดังนี้
   - ให้สิทธิ์ที่ยังขาด: `POST /permissions` (ไฟล์ role 34, โฟลเดอร์ role 6 = ดูอย่างเดียว) แล้วเก็บ `permission_id`
   - ถอนสิทธิ์ของรายการ/อีเมลที่เอาออก: `DELETE /permissions/{permission_id}`
   - ส่งอีเมลสรุปลิงก์ แยกตามหมวด ให้ผู้ที่ได้สิทธิ์ใหม่
5. ลบกลุ่ม = ถอนสิทธิ์ทั้งหมดก่อน แล้วจึงลบ

## กลไกกันความผิดพลาด

- ต้นฉบับที่เปิดไม่ได้ในรอบนั้นจะไม่ถูกแชร์เพิ่ม แต่สิทธิ์เดิมยังคงอยู่ (ไม่ถอน)
- ถ้าไฟล์เดียวกันถูกแชร์ให้อีเมลเดียวกันจากหลายกลุ่ม จะใช้สิทธิ์ร่วมกัน และไม่ถอนจนกว่าทุกกลุ่มจะเอาออก
- แชร์ไม่สำเร็จ (เช่น R910 แอดมินไม่อนุญาตแชร์ภายนอก) จะแสดงเหตุผลภาษาไทย และลองใหม่เมื่อกดแชร์ครั้งถัดไป
- หน่วงเวลาระหว่างคำขอ API (`WORKDRIVE_REQUEST_DELAY_MS` ค่าเริ่มต้น 700 ms) และถ้าเจอ 429 จะรอ 5/10/20 วินาทีแล้วลองใหม่
- มี lock กันทำงานซ้อนในกลุ่มเดียวกัน และบันทึกประวัติทุกรอบ (ตาราง `share_runs`)

## ตั้งค่า Zoho (api-console.zoho.com, DC .com)

**A) Self Client — ให้ระบบแชร์สิทธิ์** (ใช้บัญชีที่มีสิทธิ์แชร์ไฟล์ต้นฉบับ)
```
WorkDrive.files.READ,WorkDrive.files.sharing.CREATE,WorkDrive.files.sharing.DELETE
```
(หรือ `WorkDrive.files.ALL,WorkDrive.files.sharing.ALL`)

**B) Server-based Application — ให้ผู้ใช้ Login** (OpenID Connect, รับเฉพาะ `@thefirstgoodmangroup.com`)
- Homepage URL: `https://www.workdrive.trackingmouthefirst.com`
- Authorized Redirect URI: `https://www.workdrive.trackingmouthefirst.com/auth/zoho/callback`

**Admin Console ของ WorkDrive**: เปิด External Sharing → แชร์ให้อีเมลภายนอก

## ค่าใน `.env` (ดู `.env.example` — ค่าลับอยู่ใน `.env` เท่านั้น)

| ค่า | ความหมาย |
|---|---|
| `WORKDRIVE_CLIENT_ID/SECRET/REFRESH_TOKEN` | Self Client (A) |
| `ZOHO_LOGIN_CLIENT_ID/SECRET` | Server-based Application (B) |
| `WORKDRIVE_REQUEST_DELAY_MS` | หน่วงเวลาระหว่างคำขอ API |
| `WORKDRIVE_ZOHO_NOTIFICATION` | ให้ Zoho ส่งอีเมลแจ้งเองด้วยหรือไม่ (ค่าเริ่มต้น false เพราะระบบส่งอีเมลสรุปเอง) |
| `MAIL_*` | SMTP สำหรับส่งอีเมลสรุปลิงก์ |

## ติดตั้งบน Plesk

1. PHP 8.2 ขึ้นไป (pdo_mysql, zip, gd, mbstring, intl, fileinfo)
2. อัปโหลดโค้ด (รวม `public/build` ที่ build แล้วด้วย `npm run build`) แล้วรัน
   ```
   composer install --no-dev --optimize-autoloader
   php artisan migrate --force
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
   `.env` บนเซิร์ฟเวอร์: `APP_ENV=production`, `APP_DEBUG=false`
3. Hosting Settings → Document root = `<โฟลเดอร์โปรเจกต์>/public`
4. SSL/TLS → Let's Encrypt (ทั้งโดเมนมีและไม่มี www) + redirect HTTP → HTTPS
5. Hosting Settings → Preferred domain = `www.workdrive.trackingmouthefirst.com` (มี rule สำรองใน `public/.htaccess`)
6. Scheduled Tasks → ทุกนาที (`* * * * *`)
   ```
   cd /var/www/vhosts/<โดเมน>/<โฟลเดอร์โปรเจกต์> && /opt/plesk/php/8.3/bin/php artisan schedule:run
   ```
   ตัวนี้จะประมวลผลคิว (แชร์/ถอนสิทธิ์/ส่งอีเมล) ทุกนาที จึงไม่ต้องมี queue worker ค้างไว้
7. ให้ `storage/` และ `bootstrap/cache/` เขียนได้

## Login ใน Zoho CRM (Web Tab)

หน้า Login ของ Zoho ไม่ยอมให้แสดงใน iframe (`X-Frame-Options: SAMEORIGIN`) ถ้าเปิดใน Web Tab ระบบจะแสดงปุ่ม "เปิดในแท็บใหม่"
แนะนำตั้ง Web Tab ให้เปิดในแท็บใหม่ตั้งแต่แรก

## คำสั่ง

```
php artisan workdrive:check <ลิงก์หรือ ID>...   # ตรวจการเชื่อมต่อและอ่านข้อมูลไฟล์ (อ่านอย่างเดียว)
php artisan workdrive:apply "บริษัท A"          # แชร์/ถอนสิทธิ์ของกลุ่ม (--no-mail = ไม่ส่งอีเมล)
php artisan test                                # Feature test (mock Zoho API ด้วย Http::fake)
```

## อ้างอิงเอกสาร Zoho

- OpenAPI ทางการ: https://github.com/zoho/zohoworkdrive-oas (`file_folder_sharing.json`: POST/DELETE `/permissions`)
- Login: https://www.zoho.com/accounts/protocol/oauth/sign-in-using-zoho.html
