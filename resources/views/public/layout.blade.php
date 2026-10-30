<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title', $group->rootName())</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600&display=swap" rel="stylesheet">
@vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
<header class="border-b border-slate-200 bg-white">
  <div class="mx-auto flex max-w-5xl items-center gap-2 px-4 py-3">
    <span class="text-lg">🗂️</span>
    <span class="font-semibold text-slate-700">เอกสารที่แชร์ให้คุณ</span>
  </div>
</header>
<main class="mx-auto max-w-5xl px-4 py-6">
  @yield('content')
</main>
</body>
</html>
