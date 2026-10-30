@extends('workdrive.layout')
@section('title', $group->name)

@section('content')
<a href="{{ route('workdrive.index') }}" class="muted hover:underline">← กลุ่มแชร์ทั้งหมด</a>

<div class="flex flex-wrap items-start justify-between gap-3">
  <div>
    <h1 class="text-xl font-semibold">{{ $group->name }}</h1>
    @if ($group->company_name && $group->company_name !== $group->name)
      <p class="muted">บริษัท: {{ $group->company_name }}</p>
    @endif
    <p class="muted">แชร์ล่าสุด {{ $group->last_applied_at?->format('d/m/Y H:i') ?? '—' }} {{ $group->last_result }}</p>
  </div>
  <form method="post" action="{{ route('workdrive.apply', $group) }}" class="flex flex-col items-end gap-1">
    @csrf
    <button class="btn">แชร์ / อัปเดตสิทธิ์ @if ($pending) <span class="badge bg-white/20 text-white">{{ $pending }}</span> @endif</button>
    <label class="flex items-center gap-1 text-xs text-slate-500"><input type="hidden" name="notify" value="0"><input type="checkbox" name="notify" value="1" checked> ส่งอีเมลสรุปให้ผู้ได้สิทธิ์ใหม่</label>
  </form>
</div>

{{-- ลิงก์หลัก: หน้าสารบัญรวมทุกรายการที่แชร์แล้ว ส่งให้ลูกค้าลิงก์เดียว --}}
<div class="card !py-4">
  <div class="flex flex-wrap items-center gap-2">
    <span class="text-sm font-semibold">🔗 ลิงก์หลักสำหรับส่งลูกค้า</span>
    <span class="muted">· แสดงเฉพาะรายการที่แชร์สิทธิ์แล้ว แยกตามหมวด</span>
  </div>
  <div class="mt-2 flex flex-col gap-2 sm:flex-row">
    <input id="public-link" type="text" class="input font-mono !text-xs" value="{{ $publicUrl }}" readonly onfocus="this.select()">
    <div class="flex shrink-0 gap-2">
      <button type="button" class="btn" onclick="navigator.clipboard.writeText(document.getElementById('public-link').value).then(() => { this.textContent = 'คัดลอกแล้ว ✓'; setTimeout(() => this.textContent = 'Copy', 1500) })">Copy</button>
      <a class="btn-light" href="{{ $publicUrl }}" target="_blank" rel="noopener">เปิดดู ↗</a>
      <form method="post" action="{{ route('workdrive.public-link', $group) }}" onsubmit="return confirm('สร้างลิงก์ใหม่? ลิงก์เดิมที่ส่งให้ลูกค้าไปแล้วจะใช้ไม่ได้')">
        @csrf <button class="btn-light" title="ลิงก์เดิมจะใช้ไม่ได้">สร้างใหม่</button>
      </form>
    </div>
  </div>
</div>

@if ($pending)
  <div class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">มีสิทธิ์ที่ยังไม่ได้อัปเดต {{ $pending }} รายการ — กด "แชร์ / อัปเดตสิทธิ์"</div>
@endif

@if ($errorGrants->isNotEmpty())
  <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
    แชร์ไม่สำเร็จ {{ $errorGrants->count() }} รายการ (จะลองใหม่ทุกครั้งที่กดแชร์):
    <ul class="mt-1 list-disc pl-5">
      @foreach ($errorGrants->take(10) as $g)<li>{{ $g->email }}: {{ \Illuminate\Support\Str::limit($g->error, 160) }}</li>@endforeach
    </ul>
  </div>
@endif

<div class="grid gap-4 md:grid-cols-2">
  {{-- ผู้รับ --}}
  <div class="card">
    <h2 class="card-title">อีเมลผู้รับภายนอก ({{ $recipients->count() }})</h2>
    <form method="post" action="{{ route('workdrive.recipients.add', $group) }}" class="space-y-2">
      @csrf
      <textarea name="emails" rows="2" class="input" placeholder="client@company.com — ใส่ได้หลายอีเมล คั่นด้วยขึ้นบรรทัดใหม่หรือ ," required>{{ old('emails') }}</textarea>
      <button class="btn">เพิ่มผู้รับ</button>
    </form>
    <div class="mt-3 divide-y divide-slate-100">
      @foreach ($recipients as $r)
        <div class="flex items-center gap-2 py-2 text-sm">
          <div class="min-w-0 flex-1">
            <div class="truncate">{{ $r->email }}</div>
            <div class="text-xs text-slate-500">
              สิทธิ์ {{ $grantedByEmail[$r->email] ?? 0 }} รายการ · ส่งอีเมลล่าสุด {{ $r->notified_at?->format('d/m/Y H:i') ?? '—' }}
            </div>
          </div>
          <form method="post" action="{{ route('workdrive.summary', $group) }}">
            @csrf <input type="hidden" name="email" value="{{ $r->email }}"> <button class="btn-light !px-2 !py-1 !text-xs" title="ส่งอีเมลสรุปลิงก์อีกครั้ง">ส่งอีเมล</button>
          </form>
          <form method="post" action="{{ route('workdrive.recipients.remove', [$group, $r]) }}" onsubmit="return confirm('เอา {{ $r->email }} ออก? สิทธิ์ทั้งหมดของอีเมลนี้จะถูกถอนเมื่อกดแชร์')">
            @csrf @method('DELETE') <button class="btn-danger">เอาออก</button>
          </form>
        </div>
      @endforeach
    </div>
  </div>

  {{-- เพิ่มรายการ --}}
  @php $defaultCompany = old('company', $group->company_name); @endphp
  <datalist id="companies">@foreach ($companies as $c)<option value="{{ $c }}">@endforeach</datalist>
  <datalist id="cats">@foreach ($picksByCategory->keys() as $c)<option value="{{ $c }}">@endforeach</datalist>
  <div class="card space-y-5">
    <div>
      <h2 class="card-title">เพิ่มโฟลเดอร์</h2>
      <form method="post" action="{{ route('workdrive.picks.add', $group) }}" class="space-y-2">
        @csrf
        <label class="block text-sm font-medium">บริษัท</label>
        <input type="text" name="company" class="input" list="companies" value="{{ $defaultCompany }}" autocomplete="off"
               placeholder="พิมพ์เพื่อค้นหาแล้วเลือกบริษัท" required onfocus="this.select()">
        <label class="block text-sm font-medium">หมวด</label>
        <input type="text" name="category" class="input" placeholder="เช่น เอกสารแรงงาน" list="cats" value="{{ old('category') }}" required>
        <label class="block text-sm font-medium">ชื่อโฟลเดอร์ <span class="font-normal text-slate-500">(บรรทัดละ 1 ชื่อ)</span></label>
        <textarea name="sources" rows="8" class="input font-mono" required placeholder="MJ465023&#10;MJ465024&#10;MJ465031">{{ old('sources') }}</textarea>
        <button class="btn">เพิ่ม</button>
      </form>
      {{-- แสดงผลตรงนี้ด้วย เพราะข้อความด้านบนสุดของหน้ามักถูกเลื่อนพ้นจอ --}}
      @if (session('ok') || session('error'))
        <div class="mt-3 rounded-lg px-3 py-2 text-sm {{ session('error') ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-800' }}">{{ session('error') ?? session('ok') }}</div>
      @endif
      @if (session('invalid'))
        <ul class="mt-2 list-disc rounded-lg bg-amber-50 py-2 pr-3 pl-7 text-xs text-amber-800">
          @foreach (session('invalid') as $line)<li>{{ $line }}</li>@endforeach
        </ul>
      @endif
    </div>

    <div class="border-t border-slate-100 pt-4">
      <h2 class="card-title">หรืออัปโหลด Excel</h2>
      <form method="post" action="{{ route('workdrive.import', $group) }}" enctype="multipart/form-data" class="space-y-2">
        @csrf
        <input type="text" name="company" class="input" list="companies" value="{{ $defaultCompany }}" autocomplete="off"
               placeholder="บริษัท" required onfocus="this.select()">
        <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="replace" value="1"> แทนที่รายการเดิมทั้งหมด</label>
        <div class="flex flex-wrap gap-2">
          <button class="btn">นำเข้า</button>
          <a class="btn-light" href="{{ route('workdrive.template') }}">ไฟล์ตัวอย่าง</a>
        </div>
      </form>
      <p class="muted mt-2">คอลัมน์ A = หมวด, B = ชื่อโฟลเดอร์</p>
    </div>
  </div>
</div>

<div class="card">
  <h2 class="card-title">รายการที่ pick ไว้</h2>
  @php
    $pickStatus = ['ok' => ['ปกติ', 'bg-emerald-50 text-emerald-700'], 'error' => ['เปิดต้นฉบับไม่ได้', 'bg-red-50 text-red-700'], 'pending' => ['ยังไม่ได้แชร์', 'bg-slate-100 text-slate-600']];
  @endphp
  @forelse ($picksByCategory as $category => $picks)
    <h3 class="mt-4 mb-2 text-sm font-semibold first:mt-0">📁 {{ $category }} <span class="font-normal text-slate-500">({{ $picks->count() }})</span></h3>
    <div class="divide-y divide-slate-100 rounded-lg border border-slate-100">
      @foreach ($picks as $p)
        @php [$stLabel, $stClass] = $pickStatus[$p->status] ?? [$p->status, 'bg-slate-100']; @endphp
        <div class="flex flex-col gap-2 p-3 sm:flex-row sm:items-start">
          <div class="min-w-0 flex-1">
            <div class="text-sm font-medium">
              <a href="{{ $p->url() }}" target="_blank" rel="noopener" class="text-emerald-800 hover:underline">{{ $p->source_name ?? $p->source_id }}</a>
              @if ($p->source_type === 'folder') <span class="badge bg-slate-100 text-slate-600">โฟลเดอร์</span> @endif
              <a href="{{ $p->url() }}" target="_blank" rel="noopener" class="btn-light ml-1 !px-2 !py-0.5 !text-xs">เปิด ↗</a>
            </div>
            <div class="truncate font-mono text-xs text-slate-400" title="{{ $p->source }}">{{ $p->source }}</div>
            @if ($p->error) <div class="mt-1 text-xs text-red-600">{{ \Illuminate\Support\Str::limit($p->error, 160) }}</div> @endif
          </div>
          <span class="self-start text-xs text-slate-500">แชร์แล้ว {{ $grantedByResource[$p->source_id] ?? 0 }}/{{ $recipients->count() }}</span>
          <span class="badge {{ $stClass }} self-start">{{ $stLabel }}</span>
          <form method="post" action="{{ route('workdrive.picks.remove', [$group, $p]) }}" onsubmit="return confirm('เอารายการนี้ออก? สิทธิ์ที่แชร์ไว้จะถูกถอนเมื่อกดแชร์')">
            @csrf @method('DELETE') <button class="btn-danger">เอาออก</button>
          </form>
        </div>
      @endforeach
    </div>
  @empty
    <p class="muted">ยังไม่มีรายการ — อัปโหลด Excel หรือเพิ่มทีละรายการ</p>
  @endforelse
</div>

<div class="card">
  <div class="mb-3 flex items-center justify-between">
    <h2 class="card-title !mb-0">ประวัติการแชร์</h2>
    <a href="{{ route('workdrive.history', $group) }}" class="text-sm text-emerald-800 hover:underline">ดูทั้งหมด →</a>
  </div>
  @include('workdrive._runs', ['runs' => $runs])
</div>

<form method="post" action="{{ route('workdrive.destroy', $group) }}" onsubmit="return confirm('ลบกลุ่มนี้? ระบบจะถอนสิทธิ์ที่แชร์ไว้ทั้งหมดก่อนลบ (ไฟล์ต้นฉบับไม่ถูกแตะ)')">
  @csrf @method('DELETE') <button class="btn-danger">ลบกลุ่มนี้ (ถอนสิทธิ์ทั้งหมด)</button>
</form>
@endsection
