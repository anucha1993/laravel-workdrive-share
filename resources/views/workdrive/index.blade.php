@extends('workdrive.layout')
@section('title', 'กลุ่มแชร์ภายนอก')

@section('content')
<h1 class="text-xl font-semibold">กลุ่มแชร์ภายนอก</h1>

<div class="card">
  <h2 class="card-title">สร้างกลุ่มแชร์ใหม่</h2>
  <form method="post" action="{{ route('workdrive.store') }}" class="space-y-2">
    @csrf
    <input type="text" name="company" class="input" list="companies" value="{{ old('company') }}" autocomplete="off"
           placeholder="พิมพ์ค้นหาบริษัท หรือวางลิงก์โฟลเดอร์บริษัท (บริษัทใหม่)" required>
    <datalist id="companies">@foreach ($companies as $c)<option value="{{ $c }}">@endforeach</datalist>
    <div class="flex flex-col gap-2 sm:flex-row">
      <input type="text" name="name" class="input" value="{{ old('name') }}" placeholder="ชื่อกลุ่ม (เว้นว่าง = ใช้ชื่อบริษัท)">
      <button class="btn shrink-0">สร้าง</button>
    </div>
  </form>
  <div class="mt-2 flex flex-wrap items-center gap-2">
    <p class="muted">
      รายชื่อบริษัท {{ number_format($companies->count()) }} รายการ
      · อัปเดต {{ $companiesRefreshedAt ? \Illuminate\Support\Carbon::parse($companiesRefreshedAt)->diffForHumans() : '—' }} (อัปเดตอัตโนมัติทุกคืน)
    </p>
    <form method="post" action="{{ route('workdrive.companies.refresh') }}">@csrf <button class="btn-light !px-2 !py-1 !text-xs">รีเฟรชรายชื่อบริษัท</button></form>
  </div>
  <p class="muted mt-1">ระบบแชร์สิทธิ์ "ดูอย่างเดียว" บนไฟล์ต้นฉบับโดยตรง ไม่คัดลอก ไม่ย้าย และไม่แก้ไฟล์</p>
</div>

<div class="card overflow-x-auto !p-0">
  <table class="w-full text-sm">
    <thead class="bg-slate-50 text-left text-xs text-slate-500">
      <tr>
        <th class="px-4 py-2 font-medium">ชื่อกลุ่ม</th>
        <th class="px-4 py-2 font-medium">รายการ</th>
        <th class="px-4 py-2 font-medium">ผู้รับ</th>
        <th class="px-4 py-2 font-medium">สิทธิ์ที่ให้แล้ว</th>
        <th class="px-4 py-2 font-medium">แชร์ล่าสุด</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
    @forelse ($groups as $g)
      <tr class="hover:bg-slate-50">
        <td class="px-4 py-3"><a class="font-medium text-emerald-800 hover:underline" href="{{ route('workdrive.show', $g) }}">{{ $g->name }}</a></td>
        <td class="px-4 py-3">{{ $g->picks_count }}</td>
        <td class="px-4 py-3">{{ $g->recipients_count }}</td>
        <td class="px-4 py-3">{{ $g->grants_count }}</td>
        <td class="px-4 py-3 text-slate-500">{{ $g->last_applied_at?->diffForHumans() ?? '—' }}<div class="text-xs">{{ $g->last_result }}</div></td>
      </tr>
    @empty
      <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">ยังไม่มีกลุ่มแชร์</td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@endsection
