<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $group->name }} — เอกสารที่แชร์ให้คุณ</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600&display=swap" rel="stylesheet">
@vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
<main class="mx-auto max-w-3xl space-y-4 px-4 py-8">
  <div>
    <p class="muted">เอกสารที่แชร์ให้คุณ</p>
    <h1 class="text-xl font-semibold">{{ $group->name }}</h1>
  </div>

  @forelse ($items as $category => $picks)
    <div class="card">
      <h2 class="card-title">📁 {{ $category }} <span class="font-normal text-slate-500">({{ $picks->count() }})</span></h2>
      <div class="divide-y divide-slate-100">
        @foreach ($picks as $p)
          <a href="{{ $p->url() }}" target="_blank" rel="noopener" class="flex items-center gap-3 py-2 hover:bg-slate-50">
            <span>{{ $p->source_type === 'folder' ? '📂' : '📄' }}</span>
            <span class="min-w-0 flex-1 truncate text-emerald-800">{{ $p->source_name ?? $p->source_id }}</span>
            <span class="text-xs text-slate-500">เปิด ↗</span>
          </a>
        @endforeach
      </div>
    </div>
  @empty
    <div class="card text-center text-slate-500">ยังไม่มีเอกสารที่แชร์</div>
  @endforelse

  <p class="muted">
    เอกสารเปิดผ่าน Zoho WorkDrive (สิทธิ์ดูอย่างเดียว) — ครั้งแรก WorkDrive อาจให้ยืนยันตัวตนด้วยรหัส OTP ที่ส่งไปยังอีเมลที่ได้รับแชร์
  </p>
</main>
</body>
</html>
