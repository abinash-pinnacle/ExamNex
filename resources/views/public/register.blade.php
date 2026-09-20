@extends('layouts.guest')
@section('title', $test->title)
@section('content')
    <h2 class="text-xl font-semibold text-slate-800 mb-1">{{ $test->title }}</h2>
    <p class="text-sm text-slate-500 mb-1">{{ $test->duration_minutes }} min · {{ $test->total_marks }} marks</p>
    <p class="text-sm text-slate-500 mb-5">Enter your details to begin.</p>
    <form method="POST" action="{{ route('public.test', $code) }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Full name</label>
            <input name="full_name" value="{{ old('full_name') }}" required class="w-full rounded-lg border-slate-300 border px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Student ID / Roll number</label>
            <input name="student_id" value="{{ old('student_id') }}" required class="w-full rounded-lg border-slate-300 border px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Email or mobile</label>
            <input name="contact" value="{{ old('contact') }}" required class="w-full rounded-lg border-slate-300 border px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Department / batch (optional)</label>
            <input name="department" value="{{ old('department') }}" class="w-full rounded-lg border-slate-300 border px-3 py-2">
        </div>
        @if ($test->access_password)
            <div>
                <label class="block text-sm font-medium mb-1">Test Password</label>
                <input name="access_password" type="password" required placeholder="Enter the password shared with you" class="w-full rounded-lg border-slate-300 border px-3 py-2">
            </div>
        @endif
        <button class="w-full bg-brand hover:bg-brand-dark text-white font-medium py-2.5 rounded-lg">Register & Start →</button>
    </form>
@endsection
