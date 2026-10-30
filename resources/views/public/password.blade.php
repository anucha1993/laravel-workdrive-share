@extends('public.layout')

@section('content')
<div class="card mx-auto mt-10 max-w-sm">
  <h1 class="card-title">{{ $group->rootName() }}</h1>
  <p class="muted mb-3">ลิงก์นี้ป้องกันด้วยรหัสผ่าน</p>
  @if (session('error')) <p class="mb-2 text-sm text-red-600">{{ session('error') }}</p> @endif
  <form method="post" action="{{ route('public.unlock', $token) }}" class="space-y-2">
    @csrf
    <input type="password" name="password" class="input" placeholder="รหัสผ่าน" autofocus required>
    <button class="btn w-full justify-center">เปิด</button>
  </form>
</div>
@endsection
