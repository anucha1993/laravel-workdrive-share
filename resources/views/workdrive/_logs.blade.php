@php
  $actions = [
      'view' => ['เปิดลิงก์', 'bg-slate-100 text-slate-600'], 'open' => ['เปิดโฟลเดอร์', 'bg-slate-100 text-slate-600'],
      'download' => ['ดาวน์โหลด', 'bg-emerald-50 text-emerald-700'], 'zip' => ['ดาวน์โหลด ZIP', 'bg-emerald-50 text-emerald-700'],
      'unlock' => ['ใส่รหัสผ่านถูก', 'bg-sky-50 text-sky-700'], 'unlock_failed' => ['รหัสผ่านผิด', 'bg-amber-50 text-amber-700'],
      'denied' => ['ถูกปฏิเสธ', 'bg-red-50 text-red-700'],
  ];
@endphp
@if ($logs->isEmpty())
  <p class="muted">ยังไม่มีการเข้าดู</p>
@else
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <tbody class="divide-y divide-slate-100">
        @foreach ($logs as $l)
          @php [$label, $cls] = $actions[$l->action] ?? [$l->action, 'bg-slate-100']; @endphp
          <tr>
            <td class="py-1.5 pr-3 whitespace-nowrap text-slate-500">{{ $l->created_at?->format('d/m/Y H:i') }}</td>
            <td class="py-1.5 pr-3"><span class="badge {{ $cls }}">{{ $label }}</span></td>
            <td class="py-1.5 pr-3">{{ $l->name }}</td>
            <td class="py-1.5 font-mono text-xs whitespace-nowrap text-slate-400" title="{{ $l->user_agent }}">{{ $l->ip }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif
