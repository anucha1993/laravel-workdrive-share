<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'WorkDrive Share')</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600&display=swap" rel="stylesheet">
@vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
<header class="border-b border-slate-200 bg-white">
  <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3">
    <a href="{{ route('workdrive.index') }}" class="font-semibold text-emerald-800">WorkDrive แชร์ภายนอก</a>
    @auth
      <form method="post" action="{{ route('logout') }}" class="flex items-center gap-3 text-sm text-slate-500">
        @csrf <span class="hidden sm:inline">{{ auth()->user()->email }}</span>
        <button class="btn-light !px-3 !py-1">ออกจากระบบ</button>
      </form>
    @endauth
  </div>
</header>

<main class="mx-auto max-w-5xl space-y-4 px-4 py-6">
  @if (session('ok')) <div class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('ok') }}</div> @endif
  @if (session('error')) <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div> @endif
  @if (session('invalid'))
    <div class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">แถวที่นำเข้าไม่ได้:
      <ul class="mt-1 list-disc pl-5">@foreach (session('invalid') as $line)<li>{{ $line }}</li>@endforeach</ul>
    </div>
  @endif
  @if ($errors->any())
    <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">@foreach ($errors->all() as $e) {{ $e }}<br> @endforeach</div>
  @endif
  @yield('content')
</main>
</body>
</html>
