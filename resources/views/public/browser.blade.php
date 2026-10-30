@extends('public.layout')

@section('content')
@php
  $fmtSize = function ($b) {
      if ($b === null || $b === '') return '—';
      $u = ['B', 'KB', 'MB', 'GB']; $i = 0; $b = (float) $b;
      while ($b >= 1024 && $i < 3) { $b /= 1024; $i++; }
      return ($i ? number_format($b, 1) : (int) $b) . ' ' . $u[$i];
  };
  $fmtTime = fn ($ms) => $ms ? \Illuminate\Support\Carbon::createFromTimestampMs($ms)->timezone(config('app.timezone'))->format('d/m/Y H:i') : '—';
  $icon = fn ($i) => $i['is_folder'] ? '📁' : match (strtolower((string) $i['extn'])) {
      'pdf' => '📕', 'doc', 'docx' => '📘', 'xls', 'xlsx', 'csv' => '📗', 'ppt', 'pptx' => '📙',
      'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic' => '🖼️', 'zip', 'rar', '7z' => '🗜️', default => '📄',
  };
@endphp

{{-- breadcrumb --}}
<div class="flex flex-wrap items-center justify-between gap-3">
  <nav class="flex min-w-0 flex-wrap items-center gap-1 text-sm">
    <a href="{{ route('public.share', $token) }}" class="font-semibold text-emerald-800 hover:underline">{{ $group->rootName() }}</a>
    @foreach ($crumbs as $c)
      <span class="text-slate-400">›</span>
      @if ($c['url'] && !$loop->last)
        <a href="{{ $c['url'] }}" class="text-emerald-800 hover:underline">{{ $c['name'] }}</a>
      @else
        <span class="font-medium">{{ $c['name'] }}</span>
      @endif
    @endforeach
  </nav>
  @if ($items || $categories)
    <a href="{{ $zipUrl }}" class="btn shrink-0">⬇ ดาวน์โหลดทั้งหมด (ZIP)</a>
  @endif
</div>

<div class="card mt-4 overflow-x-auto !p-0">
  <table class="w-full text-sm">
    <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs text-slate-500">
      <tr>
        <th class="px-4 py-2 font-medium">Name</th>
        <th class="hidden px-4 py-2 font-medium sm:table-cell">Last Modified</th>
        <th class="hidden px-4 py-2 text-right font-medium sm:table-cell">Size</th>
        <th class="w-10 px-2 py-2"></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      @foreach ($categories as $cat)
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-2.5"><a href="{{ route('public.category', [$token, $cat]) }}" class="flex items-center gap-2 font-medium">📁 {{ $cat }}</a></td>
          <td class="hidden px-4 py-2.5 text-slate-500 sm:table-cell">—</td>
          <td class="hidden px-4 py-2.5 text-right text-slate-500 sm:table-cell">—</td>
          <td></td>
        </tr>
      @endforeach
      @foreach ($items as $i)
        @php $href = $i['is_folder'] ? route('public.folder', [$token, $i['id']]) : route('public.download', [$token, $i['id']]); @endphp
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-2.5">
            <a href="{{ $href }}" class="flex min-w-0 items-center gap-2 {{ $i['is_folder'] ? 'font-medium' : '' }}">
              <span>{{ $icon($i) }}</span><span class="truncate">{{ $i['name'] }}</span>
            </a>
            <div class="text-xs text-slate-400 sm:hidden">{{ $fmtTime($i['modified']) }} @unless ($i['is_folder']) · {{ $fmtSize($i['size']) }} @endunless</div>
          </td>
          <td class="hidden px-4 py-2.5 whitespace-nowrap text-slate-500 sm:table-cell">{{ $fmtTime($i['modified']) }}</td>
          <td class="hidden px-4 py-2.5 text-right whitespace-nowrap text-slate-500 sm:table-cell">{{ $i['is_folder'] ? '—' : $fmtSize($i['size']) }}</td>
          <td class="px-2 py-2.5 text-right">
            <a href="{{ $i['is_folder'] ? route('public.zip', [$token, $i['id']]) : route('public.download', [$token, $i['id']]) }}"
               class="text-slate-400 hover:text-emerald-700" title="{{ $i['is_folder'] ? 'ดาวน์โหลดโฟลเดอร์ (ZIP)' : 'ดาวน์โหลด' }}">⬇</a>
          </td>
        </tr>
      @endforeach
      @if (!$items && !$categories)
        <tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">ไม่มีรายการ</td></tr>
      @endif
    </tbody>
  </table>
</div>
@endsection
