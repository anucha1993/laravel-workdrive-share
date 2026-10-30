@extends('workdrive.layout')
@section('title', $folder->name)

@section('content')
<p><a href="{{ route('workdrive.index') }}">← โฟลเดอร์ทั้งหมด</a></p>
<div class="row" style="justify-content:space-between">
  <h1>{{ $folder->name }}</h1>
  <form method="post" action="{{ route('workdrive.sync', $folder) }}">@csrf <button>ซิงก์ตอนนี้</button></form>
</div>
<p class="muted">
  ไฟล์ในโฟลเดอร์แชร์: {{ $copyCount }} ·
  ซิงก์ล่าสุด: {{ $folder->last_synced_at?->format('d/m/Y H:i') ?? '—' }} {{ $folder->last_sync_result }}
  @if ($folder->wd_folder_id)
    · <a href="https://workdrive.zoho.{{ config('workdrive.dc') }}/folder/{{ $folder->wd_folder_id }}" target="_blank">เปิดใน WorkDrive</a>
  @endif
</p>

<div class="grid2">
  <div class="card">
    <h2>อัปโหลด Excel เพื่อ pick</h2>
    <form method="post" action="{{ route('workdrive.import', $folder) }}" enctype="multipart/form-data">
      @csrf
      <div class="row"><input type="file" name="file" accept=".xlsx,.xls,.csv" required></div>
      <p class="row" style="margin:10px 0">
        <label><input type="checkbox" name="replace" value="1"> แทนที่รายการเดิมทั้งหมด</label>
        <label><input type="checkbox" name="sync_now" value="1" checked> ซิงก์ทันที</label>
      </p>
      <button>นำเข้า</button>
      <a class="btn btn-light" href="{{ route('workdrive.template') }}">ดาวน์โหลดไฟล์ตัวอย่าง</a>
    </form>
    <p class="muted">คอลัมน์ A = หมวด, B = ลิงก์หรือ ID ของไฟล์/โฟลเดอร์ใน WorkDrive</p>
  </div>

  <div class="card">
    <h2>เพิ่มทีละรายการ</h2>
    <form method="post" action="{{ route('workdrive.picks.add', $folder) }}">
      @csrf
      <div class="row" style="margin-bottom:8px"><input type="text" name="category" placeholder="หมวด เช่น สัญญา" list="cats" required></div>
      <div class="row" style="margin-bottom:8px"><input type="text" name="source" placeholder="วางลิงก์ไฟล์หรือโฟลเดอร์จาก WorkDrive" required></div>
      <button>เพิ่ม</button>
      <datalist id="cats">@foreach ($picksByCategory->keys() as $c)<option value="{{ $c }}">@endforeach</datalist>
    </form>
  </div>
</div>

<div class="card">
  <h2>รายการที่ pick ไว้</h2>
  @forelse ($picksByCategory as $category => $picks)
    <h3 style="font-size:15px;margin:14px 0 6px">📁 {{ $category }} <span class="muted">({{ $picks->count() }})</span></h3>
    <table>
      @foreach ($picks as $p)
        <tr>
          <td style="width:40%">{{ $p->source_name ?? '(ยังไม่ได้ซิงก์)' }}
            @if ($p->source_type === 'folder') <span class="tag">ทั้งโฟลเดอร์</span> @endif
          </td>
          <td class="mono">{{ $p->source }}</td>
          <td style="width:110px">
            <span class="tag {{ $p->status }}">{{ ['ok' => 'ปกติ', 'error' => 'ผิดพลาด', 'pending' => 'รอซิงก์'][$p->status] ?? $p->status }}</span>
            @if ($p->error) <div class="muted">{{ \Illuminate\Support\Str::limit($p->error, 120) }}</div> @endif
          </td>
          <td style="width:70px">
            <form method="post" action="{{ route('workdrive.picks.remove', [$folder, $p]) }}" onsubmit="return confirm('เอารายการนี้ออก?')">
              @csrf @method('DELETE') <button class="btn-danger">เอาออก</button>
            </form>
          </td>
        </tr>
      @endforeach
    </table>
  @empty
    <p class="muted">ยังไม่มีรายการ — อัปโหลด Excel หรือเพิ่มทีละรายการด้านบน</p>
  @endforelse
</div>

<div class="grid2">
  <div class="card">
    <h2>แชร์ให้อีเมลภายนอก</h2>
    <form method="post" action="{{ route('workdrive.share.email', $folder) }}" class="row">
      @csrf <input type="email" name="email" placeholder="client@company.com" required> <button>แชร์</button>
    </form>
    <p class="muted">อีกฝ่ายได้รับอีเมลแจ้ง และเห็นเฉพาะโฟลเดอร์นี้ (สิทธิ์ดูอย่างเดียว)</p>
  </div>

  <div class="card">
    <h2>สร้างลิงก์แชร์</h2>
    <form method="post" action="{{ route('workdrive.share.link', $folder) }}">
      @csrf
      <div class="row" style="margin-bottom:8px">
        <input type="password" name="password" placeholder="รหัสผ่าน (ไม่บังคับ)">
        <input type="date" name="expire" title="วันหมดอายุ">
      </div>
      <button>สร้างลิงก์</button>
    </form>
    @if ($folder->share_link)
      <p class="mono" style="margin-top:10px">{{ $folder->share_link }}</p>
    @endif
  </div>
</div>

<form method="post" action="{{ route('workdrive.destroy', $folder) }}" onsubmit="return confirm('ลบออกจากระบบ? (โฟลเดอร์ใน WorkDrive จะยังอยู่)')">
  @csrf @method('DELETE') <button class="btn-danger">ลบโฟลเดอร์นี้ออกจากระบบ</button>
</form>
@endsection
