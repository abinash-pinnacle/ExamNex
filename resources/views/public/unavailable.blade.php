@extends('layouts.guest')
@section('title', 'Test unavailable')
@section('content')
    <div class="text-center py-6">
        <div class="text-4xl mb-3">🔒</div>
        <h2 class="text-lg font-semibold text-slate-800 mb-2">Test unavailable</h2>
        <p class="text-sm text-slate-500">{{ $message }}</p>
    </div>
@endsection
