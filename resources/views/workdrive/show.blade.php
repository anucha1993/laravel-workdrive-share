@extends('workdrive.layout')
@section('title', $group->name)

@section('content')
<a href="{{ route('workdrive.index') }}" class="muted hover:underline">← ลิงก์ลูกค้าทั้งหมด</a>

<div>
  <h1 class="text-xl font-semibold">{{ $group->name }}</h1>
  @if ($group->company_name && $group->company_name !== $group->name)
    <p class="muted">ลูกค้า: {{ $group->company_name }}</p>
  @endif
</div>

{{-- ลิงก์ลูกค้า --}}
<div class="card">
  <div class="flex flex-wrap items-center gap-2">
    <h2 class="card-title !mb-0">🔗 ลิงก์สำหรับส่งลูกค้า</h2>
    @if ($group->linkActive())
      <span class="badge bg-emerald-50 text-emerald-700">เปิดใช้งาน</span>
    @else
      <span class="badge bg-red-50 text-red-700">{{ $group->link_enabled ? 'หมดอายุแล้ว' : 'ปิดอยู่' }}</span>
    @endif
    @if ($group->link_password) <span class="badge bg-slate-100 text-slate-600">🔒 มีรหัสผ่าน</span> @endif
    @if ($group->link_expires_at) <span class="badge bg-slate-100 text-slate-600">หมดอายุ {{ $group->link_expires_at->format('d/m/Y') }}</span> @endif
  </div>
  <div class="mt-3 flex flex-col gap-2 sm:flex-row">
    <input id="public-link" type="text" class="input font-mono !text-xs" value="{{ $publicUrl }}" readonly onfocus="this.select()">
    <div class="flex shrink-0 gap-2">
      <button type="button" class="btn" onclick="navigator.clipboard.writeText(document.getElementById('public-link').value).then(() => { this.textContent = 'คัดลอกแล้ว ✓'; setTimeout(() => this.textContent = 'Copy', 1500) })">Copy</button>
      <a class="btn-light" href="{{ $publicUrl }}" target="_blank" rel="noopener">เปิดดู ↗</a>
      <form method="post" action="{{ route('workdrive.public-link', $group) }}" onsubmit="return confirm('สร้างลิงก์ใหม่? ลิงก์เดิมที่ส่งให้ลูกค้าไปแล้วจะใช้ไม่ได้ทันที')">
        @csrf <button class="btn-light" title="เพิกถอนลิงก์เดิม">สร้างใหม่</button>
      </form>
    </div>
  </div>

  <form method="post" action="{{ route('workdrive.link.update', $group) }}" class="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-4 sm:items-end">
    @csrf
    <label class="block text-sm sm:col-span-1">
      <span class="font-medium">รหัสผ่าน</span>
      <input type="password" name="password" class="input mt-1" placeholder="{{ $group->link_password ? '•••• (ใส่เพื่อเปลี่ยน)' : 'ไม่บังคับ' }}" autocomplete="new-password">
      @if ($group->link_password)
        <label class="mt-1 flex items-center gap-1 text-xs text-slate-500"><input type="checkbox" name="clear_password" value="1"> ยกเลิกรหัสผ่าน</label>
      @endif
    </label>
    <label class="block text-sm">
      <span class="font-medium">วันหมดอายุ</span>
      <input type="date" name="expires_at" class="input mt-1" value="{{ $group->link_expires_at?->format('Y-m-d') }}">
    </label>
    <label class="flex items-center gap-2 text-sm sm:pb-2">
      <input type="hidden" name="enabled" value="0">
      <input type="checkbox" name="enabled" value="1" @checked($group->link_enabled)> เปิดใช้งานลิงก์
    </label>
    <div><button class="btn">บันทึก</button></div>
  </form>
</div>

<div class="grid gap-4 lg:grid-cols-2">
  {{-- ติ๊กเลือกจากโฟลเดอร์ลูกค้า --}}
  <div class="card">
    <h2 class="card-title">เลือกจากโฟลเดอร์ลูกค้า</h2>
    @if (!$group->company_folder_id)
      <p class="muted">ยังไม่ได้ผูกลูกค้า — ใช้ช่องวางชื่อด้านล่างเพื่อเลือกลูกค้า</p>
    @else
      <p class="mb-2 text-sm">
        <a href="{{ route('workdrive.show', $group) }}" class="text-emerald-800 hover:underline">📁 {{ $group->company_name }}</a>
        @if ($browseName) <span class="text-slate-400">›</span> {{ $browseName }} @endif
      </p>
      @if ($browseError)
        <p class="text-sm text-red-600">{{ $browseError }}</p>
      @else
        <form method="post" action="{{ route('workdrive.picks.select', $group) }}">
          @csrf
          <input type="hidden" name="browse" value="{{ $browseId }}">
          <div class="max-h-80 divide-y divide-slate-100 overflow-y-auto rounded-lg border border-slate-100">
            @forelse ($browseItems as $i)
              @php $picked = $pickedIds->has($i['id']); @endphp
              <div class="flex items-center gap-2 px-3 py-1.5 text-sm {{ $picked ? 'bg-emerald-50' : '' }}">
                <input type="checkbox" name="ids[]" value="{{ $i['id'] }}" @disabled($picked) @checked($picked)>
                <span>{{ $i['is_folder'] ? '📁' : '📄' }}</span>
                @if ($i['is_folder'])
                  <a href="{{ route('workdrive.show', [$group, 'browse' => $i['id']]) }}" class="min-w-0 flex-1 truncate hover:underline" title="เปิดดูข้างใน">{{ $i['name'] }}</a>
                @else
                  <span class="min-w-0 flex-1 truncate">{{ $i['name'] }}</span>
                @endif
                @if ($picked) <span class="text-xs text-emerald-700">เลือกแล้ว</span> @endif
              </div>
            @empty
              <p class="px-3 py-4 text-center text-sm text-slate-500">ไม่มีรายการ</p>
            @endforelse
          </div>
          <div class="mt-2 flex flex-col gap-2 sm:flex-row">
            <input type="text" name="category" class="input" placeholder="หมวด (ไม่บังคับ)" list="cats">
            <button class="btn shrink-0">เพิ่มที่ติ๊กไว้</button>
          </div>
        </form>
        <p class="muted mt-1">กดชื่อโฟลเดอร์เพื่อดูข้างใน · Associated Contacts / Deals ไม่ต้องติ๊ก ลูกค้าจะไม่เห็น</p>
      @endif
    @endif
  </div>

  {{-- วางชื่อ / Excel --}}
  @php $defaultCompany = old('company', $group->company_name); @endphp
  <datalist id="companies">@foreach ($companies as $c)<option value="{{ $c }}">@endforeach</datalist>
  <datalist id="cats">@foreach ($picksByCategory->keys()->filter() as $c)<option value="{{ $c }}">@endforeach</datalist>
  <div class="card space-y-5">
    <div>
      <h2 class="card-title">วางชื่อโฟลเดอร์</h2>
      <form method="post" action="{{ route('workdrive.picks.add', $group) }}" class="space-y-2">
        @csrf
        <input type="text" name="company" class="input" list="companies" value="{{ $defaultCompany }}" autocomplete="off" placeholder="ลูกค้า (พิมพ์เพื่อค้นหา)" required onfocus="this.select()">
        <textarea name="sources" rows="4" class="input font-mono" required placeholder="MH468133&#10;MJ465023">{{ old('sources') }}</textarea>
        <div class="flex flex-col gap-2 sm:flex-row">
          <input type="text" name="category" class="input" placeholder="หมวด (ไม่บังคับ)" list="cats" value="{{ old('category') }}">
          <button class="btn shrink-0">เพิ่ม</button>
        </div>
      </form>
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
        <input type="text" name="company" class="input" list="companies" value="{{ $defaultCompany }}" autocomplete="off" placeholder="ลูกค้า" required onfocus="this.select()">
        <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="replace" value="1"> แทนที่รายการเดิมทั้งหมด</label>
        <div class="flex flex-wrap gap-2">
          <button class="btn">นำเข้า</button>
          <a class="btn-light" href="{{ route('workdrive.template') }}">ไฟล์ตัวอย่าง</a>
        </div>
      </form>
      <p class="muted mt-2">คอลัมน์ A = หมวด (ไม่บังคับ), B = ชื่อโฟลเดอร์</p>
    </div>
  </div>
</div>

{{-- รายการที่ลูกค้าจะเห็น --}}
<div class="card">
  <h2 class="card-title">รายการที่ลูกค้าจะเห็น ({{ $picksByCategory->flatten()->count() }})</h2>
  @forelse ($picksByCategory as $category => $picks)
    <h3 class="mt-4 mb-2 text-sm font-semibold first:mt-0">📁 {{ $category !== '' ? $category : $group->rootName() }} <span class="font-normal text-slate-500">({{ $picks->count() }})</span></h3>
    <div class="divide-y divide-slate-100 rounded-lg border border-slate-100">
      @foreach ($picks as $p)
        <div class="flex items-center gap-2 p-3">
          <div class="min-w-0 flex-1">
            <a href="{{ $p->url() }}" target="_blank" rel="noopener" class="text-sm text-emerald-800 hover:underline">{{ $p->source_type === 'file' ? '📄' : '📂' }} {{ $p->source_name ?? $p->source_id }}</a>
            <div class="truncate font-mono text-xs text-slate-400" title="{{ $p->source }}">{{ $p->source }}</div>
          </div>
          <form method="post" action="{{ route('workdrive.picks.remove', [$group, $p]) }}" onsubmit="return confirm('เอารายการนี้ออก? ลูกค้าจะไม่เห็นรายการนี้ทันที')">
            @csrf @method('DELETE') <button class="btn-danger">เอาออก</button>
          </form>
        </div>
      @endforeach
    </div>
  @empty
    <p class="muted">ยังไม่มีรายการ — ติ๊กเลือกจากโฟลเดอร์ลูกค้า หรือวางชื่อโฟลเดอร์</p>
  @endforelse
</div>

{{-- log --}}
<div class="card">
  <div class="mb-3 flex items-center justify-between">
    <h2 class="card-title !mb-0">การเข้าดู / ดาวน์โหลดของลูกค้า</h2>
    <a href="{{ route('workdrive.history', $group) }}" class="text-sm text-emerald-800 hover:underline">ดูทั้งหมด →</a>
  </div>
  @include('workdrive._logs', ['logs' => $logs])
</div>

<form method="post" action="{{ route('workdrive.destroy', $group) }}" onsubmit="return confirm('ลบลิงก์ลูกค้านี้? ลิงก์จะใช้ไม่ได้ทันที (ไฟล์ใน WorkDrive ไม่ถูกแตะ)')">
  @csrf @method('DELETE') <button class="btn-danger">ลบลิงก์ลูกค้านี้</button>
</form>
@endsection
