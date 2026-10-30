<!doctype html>
<html lang="th">
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Tahoma,'Noto Sans Thai',sans-serif;color:#1e293b;font-size:15px;line-height:1.6">
  <div style="max-width:640px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px">
    <p style="margin-top:0">เรียน ผู้รับเอกสาร</p>
    <p>{{ config('app.name') }} ได้แชร์เอกสารชุด <b>{{ $group->name }}</b> ให้คุณผ่าน Zoho WorkDrive (สิทธิ์ดูอย่างเดียว) ตามรายการด้านล่าง</p>
    <p style="margin:16px 0">
      <a href="{{ $group->publicUrl() }}" style="display:inline-block;background:#047857;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none">เปิดรายการเอกสารทั้งหมด</a>
    </p>

    @foreach ($items as $category => $picks)
      <h3 style="margin:20px 0 6px;font-size:15px;color:#047857">📁 {{ $category }}</h3>
      <ul style="margin:0;padding-left:20px">
        @foreach ($picks as $p)
          <li style="margin:4px 0">
            <a href="{{ $p->url() }}" style="color:#0f766e">{{ $p->source_name ?? $p->source_id }}</a>
            @if ($p->source_type === 'folder') <span style="color:#64748b;font-size:13px">(โฟลเดอร์)</span> @endif
          </li>
        @endforeach
      </ul>
    @endforeach

    <p style="margin-top:24px;color:#64748b;font-size:13px">
      เมื่อเปิดลิงก์ครั้งแรก WorkDrive อาจให้ยืนยันตัวตนด้วยรหัส OTP ที่ส่งไปยังอีเมลนี้
    </p>
  </div>
</body>
</html>
