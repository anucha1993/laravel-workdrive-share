@extends('workdrive.layout')
@section('title', 'ประวัติการแชร์ - ' . $group->name)

@section('content')
<a href="{{ route('workdrive.show', $group) }}" class="muted hover:underline">← {{ $group->name }}</a>
<h1 class="text-xl font-semibold">ประวัติการแชร์: {{ $group->name }}</h1>
<div class="card">
  @include('workdrive._runs', ['runs' => $runs])
  <div class="mt-4">{{ $runs->links() }}</div>
</div>
@endsection
