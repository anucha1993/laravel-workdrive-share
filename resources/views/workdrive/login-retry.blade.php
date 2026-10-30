<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>WorkDrive Share</title>
<style>
  body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:16px; background:#f8fafc; color:#1e293b; font:15px/1.6 system-ui, "Noto Sans Thai", sans-serif; text-align:center; }
  a { display:inline-block; margin-top:12px; padding:10px 20px; border-radius:8px; background:#047857; color:#fff; text-decoration:none; }
  small { color:#64748b; }
</style>
</head>
<body>
  <div>
    <p>เข้าสู่ระบบไม่สำเร็จ<br><small>{{ $message }}</small></p>
    <a href="{{ route('auth.zoho') }}">เข้าสู่ระบบด้วย Zoho อีกครั้ง</a>
  </div>
</body>
</html>
