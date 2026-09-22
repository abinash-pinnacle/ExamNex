<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Submitted · {{ $test->title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
    <style>:root{--brand-rgb: {{ \App\Models\Setting::brandRgb() }};--brand-dark-rgb: {{ \App\Models\Setting::brandDarkRgb() }};}</style>
</head>
@php
    $fmt = fn ($n) => \App\Services\SectionService::fmt($n);
    $hasSec = $sectionResults && $sectionResults->count();
    // Answer counts: from stored section results, or from the answers for plain tests.
    if ($hasSec) {
        $cCorrect = $sectionResults->sum('correct'); $cWrong = $sectionResults->sum('wrong'); $cUn = $sectionResults->sum('unanswered');
    } else {
        $cCorrect = $cWrong = $cUn = 0;
        foreach (($paper ?? []) as $item) {
            $a = $answers->get($item['question']->id);
            if (! \App\Services\SectionService::attempted($a)) { $cUn++; }
            elseif ($a->is_correct === true || ($item['question']->type === 'DESCRIPTIVE' && ($a->awarded_marks ?? 0) > 0)) { $cCorrect++; }
            else { $cWrong++; }
        }
    }
    $maxScore = $attempt->max_score ?? $test->total_marks;
@endphp
<body class="h-full bg-gradient-to-b from-[#eaf1fb] to-[#f4f8fd] text-slate-800">
<main class="app-shell overflow-y-auto">
    <div class="min-h-full flex items-center justify-center px-4 py-8">
    <div class="w-full {{ $showScore ? 'max-w-2xl' : 'max-w-lg' }} bg-white rounded-2xl shadow-sm border border-slate-100 p-6 sm:p-8">

        @if ($attempt->terminated)
            <div class="text-center">
                <div class="w-16 h-16 mx-auto rounded-full bg-rose-100 flex items-center justify-center mb-4">
                    <svg class="w-9 h-9 text-rose-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
                </div>
                <h1 class="text-2xl font-extrabold text-rose-700">Exam Terminated</h1>
                <p class="text-slate-600 mt-2">Your exam was terminated because you left the exam window / attempted malpractice. This has been recorded and reported to the administrator.</p>
            </div>

        @elseif ($showScore)
            {{-- ================= Full result (result visibility = IMMEDIATE) ================= --}}
            <div class="text-center">
                <div class="text-[11px] font-bold uppercase tracking-widest text-slate-400">Assessment Result</div>
                <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 mt-1">{{ $test->title }}</h1>
                <p class="text-sm text-slate-500 mt-1">{{ $attempt->candidate->name ?? 'Candidate' }}</p>
            </div>

            <div class="grid grid-cols-2 gap-3 mt-5">
                <div class="rounded-xl bg-slate-50 border border-slate-100 p-4 text-center">
                    <div class="text-xs text-slate-400">Overall Score</div>
                    <div class="text-3xl font-extrabold text-slate-900 mt-1">{{ $fmt($attempt->total_score ?? 0) }} <span class="text-base text-slate-400 font-semibold">/ {{ $fmt($maxScore) }}</span></div>
                </div>
                <div class="rounded-xl border p-4 text-center {{ $attempt->passed ? 'bg-emerald-50 border-emerald-200' : 'bg-rose-50 border-rose-200' }}">
                    <div class="text-xs {{ $attempt->passed ? 'text-emerald-600' : 'text-rose-600' }}">Overall Status</div>
                    <div class="text-3xl font-extrabold mt-1 {{ $attempt->passed ? 'text-emerald-700' : 'text-rose-700' }}">{{ $attempt->passed ? '✓ PASS' : '✕ FAIL' }}</div>
                </div>
            </div>
            @if (! $attempt->passed && $attempt->result_reason)
                <div class="mt-3 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-800"><b>Reason:</b> {{ $attempt->result_reason }}</div>
            @endif

            @if ($hasSec)
                <div class="mt-5">
                    <div class="text-sm font-bold text-slate-800 mb-2">Section Performance</div>
                    <div class="overflow-x-auto rounded-xl border border-slate-100">
                        <table class="w-full text-sm min-w-[480px]">
                            <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide text-left">
                                <tr><th class="px-3 py-2">Section</th><th class="px-3 py-2 text-right">Score</th><th class="px-3 py-2 text-right">Max</th><th class="px-3 py-2 text-right">Required</th><th class="px-3 py-2 text-center">Status</th></tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach ($sectionResults as $r)
                                    <tr class="{{ $r->passed === false ? 'bg-rose-50/40' : '' }}">
                                        <td class="px-3 py-2 font-medium text-slate-800">{{ $r->section_title }}@if(!$r->is_mandatory) <span class="text-[10px] text-slate-400">(optional)</span>@endif</td>
                                        <td class="px-3 py-2 text-right font-semibold">{{ $fmt($r->score) }}</td>
                                        <td class="px-3 py-2 text-right text-slate-500">{{ $fmt($r->max_score) }}</td>
                                        <td class="px-3 py-2 text-right text-slate-500">{{ $fmt($r->qualifying_marks) }}</td>
                                        <td class="px-3 py-2 text-center">
                                            @if ($r->passed === true)<span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700">✓ PASS</span>
                                            @elseif ($r->passed === false)<span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-700">✕ FAIL</span>
                                            @else<span class="text-xs text-slate-400">Pending</span>@endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-3 gap-3 mt-5">
                <div class="rounded-xl bg-emerald-50 border border-emerald-100 p-3 text-center"><div class="text-2xl font-extrabold text-emerald-700">{{ $cCorrect }}</div><div class="text-[11px] text-emerald-700/70">Correct</div></div>
                <div class="rounded-xl bg-rose-50 border border-rose-100 p-3 text-center"><div class="text-2xl font-extrabold text-rose-700">{{ $cWrong }}</div><div class="text-[11px] text-rose-700/70">Wrong</div></div>
                <div class="rounded-xl bg-slate-50 border border-slate-100 p-3 text-center"><div class="text-2xl font-extrabold text-slate-600">{{ $cUn }}</div><div class="text-[11px] text-slate-500">Unattempted</div></div>
            </div>

            <div class="mt-5 rounded-xl py-3 text-center font-extrabold text-lg {{ $attempt->passed ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white' }}">
                FINAL RESULT: {{ $attempt->passed ? 'PASS ✓' : 'FAIL ✕' }}
            </div>
            @if ($test->completion_message)<p class="text-slate-600 text-sm text-center mt-4">{{ $test->completion_message }}</p>@endif

        @else
            <div class="text-center">
                <div class="w-16 h-16 mx-auto rounded-full bg-emerald-100 flex items-center justify-center mb-4">
                    <svg class="w-9 h-9 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
                </div>
                <h1 class="text-2xl font-extrabold text-slate-900">Response Submitted</h1>
                @if ($test->completion_message)
                    <p class="text-slate-600 mt-2">{{ $test->completion_message }}</p>
                @else
                    <p class="text-slate-600 mt-2">Thank you, <b>{{ $attempt->candidate->name ?? 'candidate' }}</b>. Your response for <b>{{ $test->title }}</b> has been recorded successfully.</p>
                @endif
                <div class="bg-slate-50 rounded-xl p-4 mt-6 text-sm text-slate-500">
                    Your result will be reviewed and declared by the organisation. Results are not shown here.
                </div>
            </div>
        @endif

        <div class="text-xs text-slate-400 mt-6 text-center">Submitted on {{ $attempt->submitted_at?->ist()->format('d M Y, h:i A') }}</div>
        <div class="text-center"><a href="{{ route('candidate.home') }}" class="inline-block mt-4 text-brand font-medium hover:underline">← Back to my tests</a></div>
    </div>
    </div>
</main>
</body>
</html>
