@php
  $triggers = ['manual' => 'กดแชร์', 'cli' => 'คำสั่ง', 'delete' => 'ลบกลุ่ม'];
  $statuses = ['ok' => ['สำเร็จ', 'bg-emerald-50 text-emerald-700'], 'partial' => ['ผิดพลาดบางส่วน', 'bg-amber-50 text-amber-700'],
               'failed' => ['ล้มเหลว', 'bg-red-50 text-red-700'], 'running' => ['กำลังทำงาน', 'bg-sky-50 text-sky-700']];
@endphp
@if ($runs->isEmpty())
  <p class="muted">ยังไม่เคยแชร์</p>
@else
  <div class="divide-y divide-slate-100">
    @foreach ($runs as $r)
      @php [$stLabel, $stClass] = $statuses[$r->status] ?? [$r->status, 'bg-slate-100 text-slate-600']; @endphp
      <details class="py-2">
        <summary class="flex cursor-pointer flex-wrap items-center gap-x-3 gap-y-1 text-sm">
          <span class="text-slate-500">{{ $r->started_at?->format('d/m/Y H:i') }}</span>
          <span class="badge {{ $stClass }}">{{ $stLabel }}</span>
          <span class="text-slate-500">{{ $triggers[$r->trigger] ?? $r->trigger }}</span>
          <span>ให้สิทธิ์ {{ $r->granted }} · ถอนสิทธิ์ {{ $r->revoked }} · ผิดพลาด {{ $r->errors }}</span>
          @if ($r->finished_at) <span class="text-xs text-slate-400">{{ (int) $r->started_at->diffInSeconds($r->finished_at) }} วินาที</span> @endif
        </summary>
        <pre class="mt-2 max-h-64 overflow-auto rounded-lg bg-slate-50 p-3 text-xs whitespace-pre-wrap">{{ $r->log ?: 'ไม่มีการเปลี่ยนแปลง' }}</pre>
      </details>
    @endforeach
  </div>
@endif
