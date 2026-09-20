<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Tests · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>:root{--brand-rgb: {{ \App\Models\Setting::brandRgb() }};--brand-dark-rgb: {{ \App\Models\Setting::brandDarkRgb() }};}</style>
</head>
@php
    $appName = \App\Models\Setting::get('org_name', config('app.name'));
    $u = auth()->user();
@endphp
<body class="h-full bg-gradient-to-b from-[#eaf1fb] to-[#f4f8fd] text-slate-800">

{{-- ===== Header ===== --}}
<header class="bg-white border-b border-slate-100">
    <div class="max-w-5xl mx-auto px-6 h-20 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <x-brand-logo size="w-12 h-12" icon="w-7 h-7" />
            <div>
                <div class="text-2xl font-extrabold leading-none">@if($appName==='ExamNex')Exam<span class="text-brand">Nex</span>@else{{ $appName }}@endif</div>
                <div class="text-xs text-slate-400 mt-0.5">Online Assessment Platform</div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-500 to-blue-700 text-white text-sm font-bold flex items-center justify-center">{{ strtoupper(mb_substr($u->name,0,2)) }}</span>
                <span class="hidden sm:block text-sm font-semibold text-slate-700">{{ $u->name }}</span>
            </div>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium">Logout</button>
            </form>
        </div>
    </div>
</header>

<main class="max-w-5xl mx-auto px-6 py-8">
    @include('partials.flash')

    <div class="mb-6">
        <h1 class="text-3xl font-extrabold text-slate-900">Welcome, {{ explode(' ', $u->name)[0] }}! 👋</h1>
        <p class="text-slate-500 mt-1">Here are the tests assigned to you.</p>
    </div>

    <div class="space-y-4">
        @forelse ($tests as $test)
            @php
                $attempt = $attempts->get($test->id);
                $inProgress = $attempt && $attempt->status === 'IN_PROGRESS';
                $done = $attempt && in_array($attempt->status, ['SUBMITTED','AUTO_SUBMITTED','EVALUATED']);
                $initials = strtoupper(mb_substr(preg_replace('/[^A-Za-z0-9]/', '', $test->title) ?: 'EX', 0, 2));
            @endphp
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 flex flex-col sm:flex-row sm:items-center gap-4">
                {{-- icon tile --}}
                <div class="w-14 h-14 rounded-2xl shrink-0 flex items-center justify-center text-white font-extrabold text-lg shadow"
                     style="background:radial-gradient(circle at 50% 40%, #1e3a8a 0%, #0b1e42 75%);">{{ $initials }}</div>

                {{-- info --}}
                <div class="min-w-0 flex-1">
                    <h2 class="text-lg font-bold text-slate-900 break-words">{{ $test->title }}</h2>
                    <p class="text-sm text-slate-500 mt-0.5">
                        {{ $test->test_questions_count }} questions · {{ $test->duration_minutes }} min · {{ $test->total_marks }} marks
                    </p>
                    <div class="mt-2">
                        @if ($inProgress)
                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-amber-100 text-amber-700"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> In progress</span>
                        @elseif ($attempt && $attempt->status === 'EVALUATED')
                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Evaluated</span>
                        @elseif ($done)
                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-sky-100 text-sky-700"><span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span> Submitted</span>
                        @else
                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Available now</span>
                        @endif
                    </div>
                </div>

                {{-- action --}}
                <div class="shrink-0">
                    @if ($inProgress)
                        <a href="{{ route('attempt.run', $attempt->id) }}" class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white font-semibold px-6 py-3 rounded-xl">Resume <span>→</span></a>
                    @elseif ($done)
                        <a href="{{ route('attempt.result', $attempt->id) }}" class="inline-flex items-center gap-2 border border-slate-200 hover:bg-slate-50 text-slate-700 font-semibold px-6 py-3 rounded-xl">View result</a>
                    @else
                        <a href="{{ route('candidate.intro', $test->id) }}" class="inline-flex items-center gap-2 bg-brand hover:bg-brand-dark text-white font-semibold px-6 py-3 rounded-xl shadow-lg" style="box-shadow:0 10px 20px -8px rgb(var(--brand-rgb)/.6)">Start <span>→</span></a>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-12 text-center">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-slate-100 flex items-center justify-center mb-3">
                    <svg class="w-7 h-7 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 12h6M9 16h6M6 3h9l5 5v13H6z"/></svg>
                </div>
                <p class="text-slate-500">No tests assigned to you yet.</p>
            </div>
        @endforelse
    </div>
</main>

<footer class="max-w-5xl mx-auto px-6 py-6 flex items-center justify-between text-xs text-slate-400">
    <span>© {{ date('Y') }} PinTek Digital. ExamNex is a product of PinTek Digital. All rights reserved.</span>
    <span class="flex gap-4"><a href="#" class="hover:text-slate-600">Privacy</a><a href="#" class="hover:text-slate-600">Terms</a><a href="mailto:support@examnex.test" class="hover:text-slate-600">Support</a></span>
</footer>
</body>
</html>
