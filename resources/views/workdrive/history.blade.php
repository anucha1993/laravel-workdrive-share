@extends('workdrive.layout')
@section('title', 'การเข้าดู - ' . $group->name)

@section('content')
<a href="{{ route('workdrive.show', $group) }}" class="muted hover:underline">← {{ $group->name }}</a>
<h1 class="text-xl font-semibold">การเข้าดู / ดาวน์โหลด: {{ $group->name }}</h1>
<div class="card">
  @include('workdrive._logs', ['logs' => $logs])
  <div class="mt-4">{{ $logs->links() }}</div>
</div>
@endsection
