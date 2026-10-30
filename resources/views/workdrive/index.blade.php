@extends('workdrive.layout')
@section('title', 'ลิงก์ลูกค้า')

@section('content')
<h1 class="text-xl font-semibold">ลิงก์ลูกค้า</h1>

<div class="card">
  <h2 class="card-title">สร้างลิงก์ลูกค้าใหม่</h2>
  <form method="post" action="{{ route('workdrive.store') }}" class="space-y-2">
    @csrf
    <input type="text" name="company" class="input" list="companies" value="{{ old('company') }}" autocomplete="off"
           placeholder="พิมพ์ค้นหาลูกค้า (Account) หรือวางลิงก์โฟลเดอร์ลูกค้า" required>
    <datalist id="companies">@foreach ($companies as $c)<option value="{{ $c }}">@endforeach</datalist>
    <div class="flex flex-col gap-2 sm:flex-row">
      <input type="text" name="name" class="input" value="{{ old('name') }}" placeholder="ชื่อเรียก (เว้นว่าง = ใช้ชื่อลูกค้า)">
      <button class="btn shrink-0">สร้าง</button>
    </div>
  </form>
  <div class="mt-2 flex flex-wrap items-center gap-2">
    <p class="muted">
      รายชื่อลูกค้า {{ number_format($companies->count()) }} รายการ
      · อัปเดต {{ $companiesRefreshedAt ? \Illuminate\Support\Carbon::parse($companiesRefreshedAt)->diffForHumans() : '—' }} (อัปเดตอัตโนมัติทุกคืน)
    </p>
    <form method="post" action="{{ route('workdrive.companies.refresh') }}">@csrf <button class="btn-light !px-2 !py-1 !text-xs">รีเฟรชรายชื่อ</button></form>
  </div>
  <p class="muted mt-1">ลูกค้าเปิดดู/ดาวน์โหลดผ่านลิงก์ของเรา เห็นเฉพาะรายการที่เลือก — ไฟล์ใน WorkDrive ไม่ถูกคัดลอก ย้าย หรือแก้</p>
</div>

<div class="card overflow-x-auto !p-0">
  <table class="w-full text-sm">
    <thead class="bg-slate-50 text-left text-xs text-slate-500">
      <tr>
        <th class="px-4 py-2 font-medium">ชื่อ</th>
        <th class="px-4 py-2 font-medium">รายการ</th>
        <th class="px-4 py-2 font-medium">สถานะลิงก์</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
    @forelse ($groups as $g)
      <tr class="hover:bg-slate-50">
        <td class="px-4 py-3"><a class="font-medium text-emerald-800 hover:underline" href="{{ route('workdrive.show', $g) }}">{{ $g->name }}</a></td>
        <td class="px-4 py-3">{{ $g->picks_count }}</td>
        <td class="px-4 py-3">
          @if ($g->linkActive()) <span class="badge bg-emerald-50 text-emerald-700">เปิด</span> @else <span class="badge bg-red-50 text-red-700">ปิด/หมดอายุ</span> @endif
          @if ($g->link_password) <span class="badge bg-slate-100 text-slate-600">🔒</span> @endif
        </td>
      </tr>
    @empty
      <tr><td colspan="3" class="px-4 py-6 text-center text-slate-500">ยังไม่มีลิงก์ลูกค้า</td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@endsection
