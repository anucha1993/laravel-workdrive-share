@extends('workdrive.layout')
@section('title', 'โฟลเดอร์แชร์ภายนอก')

@section('content')
<h1>โฟลเดอร์แชร์ภายนอก</h1>

<div class="card">
  <h2>สร้างโฟลเดอร์แชร์ใหม่</h2>
  <form method="post" action="{{ route('workdrive.store') }}" class="row">
    @csrf
    <input type="text" name="name" placeholder="เช่น บริษัท A" required>
    <button>สร้าง</button>
  </form>
  <p class="muted">ระบบจะสร้างโฟลเดอร์ชื่อนี้ใต้โฟลเดอร์ "แชร์ภายนอก" ใน WorkDrive ให้อัตโนมัติ</p>
</div>

<div class="card">
  <table>
    <tr><th>ชื่อ</th><th>รายการที่ pick</th><th>ไฟล์ที่ซิงก์</th><th>ซิงก์ล่าสุด</th></tr>
    @forelse ($folders as $f)
      <tr>
        <td><a href="{{ route('workdrive.show', $f) }}">{{ $f->name }}</a></td>
        <td>{{ $f->picks_count }}</td>
        <td>{{ $f->copies_count }}</td>
        <td class="muted">{{ $f->last_synced_at?->diffForHumans() ?? '—' }}<br>{{ $f->last_sync_result }}</td>
      </tr>
    @empty
      <tr><td colspan="4" class="muted">ยังไม่มีโฟลเดอร์แชร์</td></tr>
    @endforelse
  </table>
</div>
@endsection
