<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'WorkDrive Share')</title>
<style>
  :root { --bg:#f6f7f9; --card:#fff; --text:#1d2330; --muted:#6b7280; --line:#e5e7eb; --pri:#1f7a4d; --err:#b42318; --okbg:#ecfdf3; --errbg:#fef3f2; }
  * { box-sizing: border-box; }
  body { margin:0; background:var(--bg); color:var(--text); font:15px/1.55 system-ui, "Noto Sans Thai", sans-serif; }
  .wrap { max-width:1000px; margin:0 auto; padding:24px 16px; }
  h1 { font-size:22px; margin:0 0 16px; } h2 { font-size:16px; margin:0 0 12px; }
  .card { background:var(--card); border:1px solid var(--line); border-radius:10px; padding:18px; margin-bottom:16px; }
  .row { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
  input[type=text], input[type=email], input[type=password], input[type=date], input[type=file] { padding:8px 10px; border:1px solid var(--line); border-radius:6px; font:inherit; min-width:0; flex:1; }
  button, .btn { background:var(--pri); color:#fff; border:0; border-radius:6px; padding:8px 14px; font:inherit; cursor:pointer; text-decoration:none; display:inline-block; }
  .btn-light { background:#fff; color:var(--text); border:1px solid var(--line); }
  .btn-danger { background:#fff; color:var(--err); border:1px solid #f3c1bb; padding:4px 10px; font-size:13px; }
  table { width:100%; border-collapse:collapse; } th, td { text-align:left; padding:8px; border-bottom:1px solid var(--line); vertical-align:top; }
  th { color:var(--muted); font-weight:500; font-size:13px; }
  .muted { color:var(--muted); font-size:13px; } .mono { font-family:ui-monospace, monospace; font-size:12px; word-break:break-all; }
  .flash { padding:10px 14px; border-radius:8px; margin-bottom:16px; } .flash.ok { background:var(--okbg); } .flash.err { background:var(--errbg); color:var(--err); }
  .tag { display:inline-block; padding:1px 8px; border-radius:99px; font-size:12px; background:#eef2f7; }
  .tag.ok { background:var(--okbg); color:var(--pri); } .tag.error { background:var(--errbg); color:var(--err); }
  .grid2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; } @media (max-width:720px){ .grid2 { grid-template-columns:1fr; } }
</style>
</head>
<body>
<div class="wrap">
  @auth
    <form method="post" action="{{ route('logout') }}" class="row muted" style="justify-content:flex-end;margin-bottom:8px">
      @csrf {{ auth()->user()->email }} <button class="btn-light" style="padding:4px 10px">ออกจากระบบ</button>
    </form>
  @endauth
  @if (session('ok')) <div class="flash ok">{{ session('ok') }}</div> @endif
  @if (session('error')) <div class="flash err">{{ session('error') }}</div> @endif
  @if (session('invalid'))
    <div class="flash err">แถวที่นำเข้าไม่ได้:<br>@foreach (session('invalid') as $line) {{ $line }}<br> @endforeach</div>
  @endif
  @if ($errors->any()) <div class="flash err">@foreach ($errors->all() as $e) {{ $e }}<br> @endforeach</div> @endif
  @yield('content')
</div>
</body>
</html>
