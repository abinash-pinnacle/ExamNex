<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $test->title }} · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Caveat:wght@600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
    <style>:root{--brand-rgb: {{ \App\Models\Setting::brandRgb() }};--brand-dark-rgb: {{ \App\Models\Setting::brandDarkRgb() }};} .hand{font-family:'Caveat',cursive;}</style>
</head>
@php
    $appName = \App\Models\Setting::get('org_name', config('app.name'));
    $conductedBy = \App\Models\Setting::get('conducted_by');
    $conductedSub = \App\Models\Setting::get('conducted_by_sub');
    $secList = $test->usesSections() ? $test->sections : collect();
    $qCount = $secList->count() ? $secList->sum('question_count') : ($test->test_questions_count ?? $test->testQuestions()->count());
    $durMin = $secList->count() ? \App\Services\SectionService::durationMinutes($test) : $test->duration_minutes;
    $fmt = fn ($n) => \App\Services\SectionService::fmt($n);
    $subtitle = $test->description ?: ($test->subject ?: 'Online Assessment');
    $tag1 = $test->category ?: 'Assessment';
    $now = now();
    $status = 'available'; $statusLabel = 'Available Now';
    if ($test->starts_at && $now->lt($test->starts_at)) { $status = 'soon'; $statusLabel = 'Opens ' . $test->starts_at->format('d M, H:i'); }
    elseif ($test->ends_at && $now->gt($test->ends_at)) { $status = 'closed'; $statusLabel = 'Closed'; }
    $inProgress = $attempt && $attempt->status === 'IN_PROGRESS';
    $usedUp = $priorCount >= $test->max_attempts && ! $inProgress;
    $canStart = $status === 'available' && ! $usedUp;
@endphp
<body class="h-full overflow-x-hidden bg-gradient-to-b from-[#eaf1fb] to-[#f4f8fd] text-slate-800">
<div class="app-shell flex flex-col overflow-hidden">

    {{-- ===== Header ===== --}}
    <header class="bg-white border-b border-slate-100 shrink-0">
        <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <x-brand-logo size="w-10 h-10" icon="w-6 h-6" />
                <div>
                    <div class="text-xl font-extrabold leading-none">@if($appName==='ExamNex')Exam<span class="text-brand">Nex</span>@else{{ $appName }}@endif</div>
                    <div class="text-[11px] text-slate-400 mt-0.5">Online Assessment Platform</div>
                </div>
            </div>
            <div class="flex items-center gap-2 text-right">
                <svg class="w-5 h-5 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                <div class="text-sm leading-tight">
                    <div class="font-semibold text-slate-700" id="clock-date">{{ $now->format('l, j F Y') }}</div>
                    <div class="text-slate-400 text-xs" id="clock-time">{{ $now->format('h:i A') }}</div>
                </div>
            </div>
        </div>
    </header>

    {{-- ===== Content (fills remaining height, centered) ===== --}}
    <main class="flex-1 min-h-0 overflow-y-auto">
    <div class="min-h-full flex flex-col justify-center max-w-6xl w-full mx-auto px-6 py-4">
        {{-- Hero --}}
        <div class="relative text-center mb-4">
            <span class="hand hidden lg:block absolute left-0 top-0 text-xl text-blue-300 leading-tight -rotate-6">Assess<br>Learn<br>Grow</span>
            <span class="hand hidden lg:block absolute right-0 top-0 text-xl text-blue-300 leading-tight rotate-6 text-right">Your<br>Skills<br>Build Tomorrow</span>
            <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900">Welcome to Your Assessment!</h1>
            <p class="text-slate-500 text-sm mt-1">Read the details below and start your test when you are ready.</p>
            <div class="w-14 h-1 bg-brand rounded-full mx-auto mt-2"></div>
        </div>

        @include('partials.flash')

        {{-- Main test card --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 mb-4">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-center">
                {{-- Details --}}
                <div class="lg:col-span-8">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-xl bg-blue-50 text-brand flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 12h6M9 16h6M6 3h9l5 5v13H6z"/></svg>
                        </span>
                        <h2 class="text-xl md:text-2xl font-extrabold text-slate-900 break-words min-w-0">{{ $test->title }}</h2>
                    </div>
                    <p class="text-slate-500 text-sm mt-1.5 break-words">{{ $subtitle }}</p>
                    <div class="flex flex-wrap gap-2 mt-2">
                        <span class="px-3 py-1 rounded-lg text-xs font-semibold" style="background:rgb(var(--brand-rgb)/.1);color:rgb(var(--brand-rgb))">{{ $tag1 }}</span>
                        <span class="px-3 py-1 rounded-lg text-xs font-medium bg-slate-100 text-slate-500">Online Test</span>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4">
                        @php
                            $meta = [
                                ['bg-blue-50 text-brand', 'M9 12h6M9 16h6M6 3h9l5 5v13H6z', $qCount, 'Questions'],
                                ['bg-blue-50 text-brand', 'M12 7v5l3 2M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z', $durMin.' Min', 'Duration'],
                                ['bg-amber-50 text-amber-500', 'M12 2l2.9 6.3 6.9.6-5.2 4.6 1.6 6.8L12 17.8 5.8 20.9l1.6-6.8L2.2 8.9l6.9-.6z', $test->total_marks, 'Total Marks'],
                                ['bg-slate-100 text-slate-500', 'M4 5h16v10H4zM2 19h20', 'Online', 'Assessment'],
                            ];
                        @endphp
                        @foreach ($meta as [$cls,$icon,$val,$lbl])
                            <div class="flex items-center gap-2">
                                <span class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 {{ $cls }}">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                                </span>
                                <div class="min-w-0">
                                    <div class="font-bold text-slate-800 text-sm leading-tight truncate">{{ $val }}</div>
                                    <div class="text-[11px] text-slate-400">{{ $lbl }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Conducted by + start --}}
                <div class="lg:col-span-4 lg:border-l lg:border-slate-100 lg:pl-5">
                    <div class="text-xs text-slate-400 mb-1">Conducted by</div>
                    <div class="flex items-center gap-2.5 mb-2">
                        <span class="w-10 h-10 rounded-xl bg-blue-50 text-brand flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21h18M6 21V9l6-4 6 4v12M9 21v-4h6v4"/></svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-bold text-slate-800 text-sm leading-tight truncate">{{ $conductedBy }}</div>
                            <div class="text-xs text-slate-400 truncate">{{ $conductedSub }}</div>
                        </div>
                    </div>
                    <div class="text-[11px] text-slate-400 mb-2">Powered by <span class="font-bold text-slate-700">Exam<span class="text-brand">Nex</span></span></div>

                    @if ($status === 'available')
                        <div class="rounded-xl bg-emerald-50 text-emerald-700 font-semibold text-center py-2 mb-2 text-sm flex items-center justify-center gap-2"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Available Now</div>
                    @elseif ($status === 'soon')
                        <div class="rounded-xl bg-amber-50 text-amber-700 font-semibold text-center py-2 mb-2 text-sm">{{ $statusLabel }}</div>
                    @else
                        <div class="rounded-xl bg-rose-50 text-rose-700 font-semibold text-center py-2 mb-2 text-sm">{{ $statusLabel }}</div>
                    @endif

                    @if ($inProgress)
                        <a href="{{ route('attempt.run', $attempt->id) }}" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold py-3 rounded-xl flex items-center justify-center gap-2">Resume Test <span>→</span></a>
                    @elseif ($usedUp)
                        <div class="w-full bg-slate-100 text-slate-400 font-semibold py-3 rounded-xl text-center">All attempts used</div>
                    @elseif ($canStart)
                        <form method="POST" action="{{ route('attempt.start', $test->id) }}">@csrf
                            <input type="hidden" name="system_check" value="1">
                            <button class="w-full bg-brand hover:bg-brand-dark text-white font-semibold py-3 rounded-xl flex items-center justify-center gap-2 shadow-lg" style="box-shadow:0 10px 20px -8px rgb(var(--brand-rgb)/.6)">Start Test <span>→</span></button>
                        </form>
                    @else
                        <div class="w-full bg-slate-100 text-slate-400 font-semibold py-3 rounded-xl text-center">Not available</div>
                    @endif
                    @if (! $inProgress && ! $usedUp)
                        <p class="text-[11px] text-slate-400 text-center mt-1.5">Attempts: {{ $priorCount }}/{{ $test->max_attempts }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Exam sections (section-wise tests) --}}
        @if ($secList->count())
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 mb-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 mb-3">
                <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-violet-100 text-violet-600 flex items-center justify-center"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg></span>
                    Exam Sections
                </h3>
                <span class="text-xs text-slate-400">{{ $test->section_navigation === 'SEQUENTIAL' ? 'Sections must be completed in order' : 'You can move freely between sections' }}{{ $test->timer_mode === 'SECTION' ? ' · each section is timed separately' : '' }}</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach ($secList as $s)
                    <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3">
                        <div class="text-[11px] font-bold text-brand uppercase tracking-wide truncate">{{ $s->title }}</div>
                        <div class="text-sm font-semibold text-slate-800 mt-1">{{ $s->question_count }} Questions <span class="text-slate-400 font-normal">· {{ $s->maxMarks() }} marks</span></div>
                        <div class="text-xs text-slate-500 mt-0.5">Qualifying: <b class="text-slate-700">{{ $fmt($s->qualifying_marks) }} marks</b>@if ($test->timer_mode === 'SECTION' && $s->duration_minutes) · {{ $s->duration_minutes }} min @endif @if ($s->negative_marks > 0) · −{{ $fmt($s->negative_marks) }} per wrong @endif</div>
                        @if ($s->description)<div class="text-[11px] text-slate-400 mt-1 leading-snug">{{ $s->description }}</div>@endif
                    </div>
                @endforeach
            </div>
            <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-500 mt-3 pt-3 border-t border-slate-100">
                <span>Total Questions: <b class="text-slate-800">{{ $qCount }}</b></span>
                <span>Total Marks: <b class="text-slate-800">{{ $test->total_marks }}</b></span>
                <span>Duration: <b class="text-slate-800">{{ $durMin }} Minutes</b></span>
                @if ($test->passing_marks > 0)<span>Overall Qualifying: <b class="text-slate-800">{{ $test->passing_marks }} marks</b></span>@endif
                <span class="text-amber-600 font-medium">You must qualify in every section{{ $test->passing_marks > 0 ? ' and overall' : '' }} to pass.</span>
            </div>
        </div>
        @endif

        {{-- Instructions --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 mb-4">
            <div class="flex items-center gap-2 mb-3">
                <span class="w-6 h-6 rounded-full bg-brand text-white flex items-center justify-center text-xs font-bold">i</span>
                <h3 class="text-base font-bold text-slate-800">Important Instructions</h3>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                @php
                    $ins = [
                        ['bg-blue-50 text-brand', 'M5 13a10 10 0 0 1 14 0M8.5 16.5a5 5 0 0 1 7 0M12 20h.01', 'Stable internet', 'Avoid network issues.'],
                        ['bg-emerald-50 text-emerald-600', 'M4 5h16v10H4zM2 19h20', 'Keep browser open', 'Do not close or refresh.'],
                        ['bg-amber-50 text-amber-500', 'M12 7v5l3 2M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z', 'Complete in time', 'You have '.$durMin.' minutes.'],
                        ['bg-rose-50 text-rose-500', 'M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z', 'No unfair means', 'May lead to disqualification.'],
                        ['bg-violet-50 text-violet-600', 'M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8zM14 3v5h5', 'Submit in time', 'Submit before timer ends.'],
                    ];
                @endphp
                @foreach ($ins as [$cls,$icon,$t,$d])
                    <div class="flex items-start gap-2.5">
                        <span class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 {{ $cls }}">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-slate-800 text-xs leading-tight">{{ $t }}</div>
                            <div class="text-[11px] text-slate-400 mt-0.5 leading-tight">{{ $d }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Warning --}}
        <div class="bg-rose-50 border border-rose-200 rounded-xl p-3.5 flex items-center gap-3">
            <svg class="w-6 h-6 text-rose-500 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
            <p class="text-xs text-slate-600 leading-snug">
                <span class="font-bold text-rose-700">Do Not Switch Tabs or Windows —</span>
                leaving this page during the test may terminate your session and you will be <span class="font-bold text-rose-600">marked as FAIL</span>. Stay here until you submit.
            </p>
        </div>
    </div>
    </main>

    <footer class="shrink-0 max-w-6xl mx-auto w-full px-6 py-3 flex items-center justify-center text-center text-[11px] text-slate-400">
        <span>© {{ date('Y') }} PinTek Digital. ExamNex is a product of PinTek Digital. All rights reserved.</span>
    </footer>
</div>

<script>
    function updateClock() {
        const d = new Date();
        const de = document.getElementById('clock-date'), te = document.getElementById('clock-time');
        if (de) de.textContent = d.toLocaleDateString('en-US', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        if (te) te.textContent = d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    }
    updateClock();
    setInterval(updateClock, 1000);
</script>
</body>
</html>
