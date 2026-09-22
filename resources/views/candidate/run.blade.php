<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $test->title }} · Exam</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Caveat:wght@600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
    <style>
        :root{--brand-rgb: {{ \App\Models\Setting::brandRgb() }};--brand-dark-rgb: {{ \App\Models\Setting::brandDarkRgb() }};}
        .hand{font-family:'Caveat',cursive;}
        /* Anti-copy: block selection everywhere except answer fields (when enabled) */
        body.no-copy{ -webkit-user-select:none; -moz-user-select:none; user-select:none; }
        body.no-copy input, body.no-copy textarea{ -webkit-user-select:text; -moz-user-select:text; user-select:text; }
        .blurred{ filter: blur(10px); }
        .sec-tabs::-webkit-scrollbar{ height:4px; } .sec-tabs::-webkit-scrollbar-thumb{ background:#cbd5e1; border-radius:4px; }
    </style>
</head>
@php
    $appName = \App\Models\Setting::get('org_name', config('app.name'));
    $conductedBy = \App\Models\Setting::get('conducted_by');
    $conductedSub = \App\Models\Setting::get('conducted_by_sub');
    $subtitle = $test->description ?: ($test->subject ?: 'Online Assessment');
    $tag1 = $test->category ?: 'Assessment';

    // ---- Section-wise paper helpers (null/empty for plain tests) ----
    $hasSections = ! empty($sections);
    $qIndex = [];                       // question id -> global index in $paper
    foreach ($paper as $i => $item) { $qIndex[$item['question']->id] = $i; }
    $qSec = []; $qPos = [];             // question id -> section index / position within section
    if ($hasSections) {
        foreach ($sections as $si => $s) {
            foreach ($s['qids'] as $pi => $qid) { $qSec[$qid] = $si; $qPos[$qid] = $pi; }
        }
    }
    $startIndex = 0;
    if ($hasSections) {
        $cs = $sections[$currentSection] ?? $sections[0];
        $startIndex = $qIndex[$cs['qids'][0] ?? null] ?? 0;
    }
    $secTimer = $hasSections && $test->timer_mode === 'SECTION';
    $serverNav = $hasSections && ($secTimer || $test->section_navigation === 'SEQUENTIAL');
    $locksOnAdvance = $hasSections && ($secTimer || ($test->section_navigation === 'SEQUENTIAL' && ! $test->allow_section_return));
    // Pre-computed JSON for the client (Blade's @json() cannot take nested closures reliably).
    $sectionsJson = json_encode($hasSections ? array_map(fn ($s) => [
        'id' => $s['id'], 'title' => $s['title'], 'count' => count($s['qids']),
        'accessible' => $s['accessible'], 'locked' => $s['locked'],
    ], $sections) : []);
    $qsecJson = json_encode($hasSections ? array_map(fn ($p) => $qSec[$p['question']->id] ?? 0, $paper) : []);
@endphp
<body class="h-full overflow-hidden bg-gradient-to-b from-[#eaf1fb] to-[#f4f8fd] text-slate-800 {{ $test->detect_copy ? 'no-copy' : '' }}">
<div class="app-shell flex flex-col">

    {{-- ===== Header ===== --}}
    <header class="bg-white border-b border-slate-100 shrink-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-3 sm:gap-4">
            <div class="flex items-center gap-3 shrink-0 min-w-0">
                <x-brand-logo size="w-11 h-11" icon="w-6 h-6" />
                <div class="leading-tight min-w-0">
                    <div class="text-[11px] text-slate-400">Conducted by</div>
                    <div class="text-base font-extrabold text-slate-900 leading-none truncate">{{ $conductedBy }}</div>
                    <div class="text-[11px] text-slate-400 mt-0.5 truncate">{{ $conductedSub }}</div>
                </div>
            </div>
            <div class="text-center min-w-0 hidden md:block">
                <div class="font-extrabold text-slate-900 truncate">{{ $test->title }}</div>
                <div class="text-xs text-slate-400 truncate">{{ $subtitle }}</div>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                {{-- compact timer in the header (visible on mobile where the sidebar sits below) --}}
                <span class="lg:hidden flex items-center gap-1.5 border border-slate-200 rounded-lg px-2.5 py-1.5 text-sm font-bold font-mono text-brand" id="timerMini">--:--</span>
                <span class="hidden lg:flex items-center gap-2 border border-slate-200 rounded-lg px-3 py-1.5 text-sm font-semibold text-slate-600">
                    <svg class="w-4 h-4 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21h18M6 21V9l6-4 6 4v12"/></svg>{{ $tag1 }}
                </span>
                <span class="hand hidden xl:block text-base text-blue-300 leading-none text-right">Focus<br>Solve<br>Succeed</span>
            </div>
        </div>

        @if ($hasSections)
        {{-- ===== Section tabs ===== --}}
        <div class="border-t border-slate-100 bg-slate-50/70">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 py-2 flex items-center gap-2 overflow-x-auto sec-tabs">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wide shrink-0 hidden sm:inline">Sections</span>
                @foreach ($sections as $si => $s)
                    <button type="button" id="tab-{{ $si }}" onclick="goSection({{ $si }})" {{ $s['accessible'] ? '' : 'disabled' }}
                            class="sec-tab shrink-0 inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs sm:text-sm font-semibold border transition whitespace-nowrap {{ $s['accessible'] ? '' : 'opacity-50 cursor-not-allowed' }}"
                            title="{{ $s['locked'] ? 'Locked' : ($s['accessible'] ? $s['title'] : 'Opens after the previous section') }}">
                        @if ($s['locked'])<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>@endif
                        {{ $s['title'] }}
                        <span class="sec-tab-count text-[10px] font-bold opacity-70" id="tabcount-{{ $si }}">0/{{ count($s['qids']) }}</span>
                    </button>
                @endforeach
            </div>
        </div>
        @endif
    </header>

    <div id="warn" class="hidden fixed top-16 left-1/2 -translate-x-1/2 z-50 bg-rose-600 text-white text-sm py-1.5 px-4 rounded-b-lg shadow">⚠ You left the exam window — this has been logged.</div>

    {{-- Malpractice warning modal --}}
    <div id="cheatModal" class="hidden fixed inset-0 z-[60] bg-black/50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 text-center">
            <div class="w-14 h-14 mx-auto rounded-full bg-rose-100 flex items-center justify-center mb-3">
                <svg class="w-8 h-8 text-rose-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
            </div>
            <h3 class="text-xl font-bold text-rose-700">Warning!</h3>
            <p class="text-slate-600 mt-2" id="cheatMsg">You left the exam window. This is not allowed.</p>
            <p class="text-sm font-semibold text-rose-600 mt-2" id="cheatCount"></p>
            <button onclick="dismissCheat()" class="mt-5 bg-brand hover:bg-brand-dark text-white px-6 py-2.5 rounded-xl font-semibold">I understand, continue</button>
        </div>
    </div>

    {{-- Termination overlay --}}
    <div id="termOverlay" class="hidden fixed inset-0 z-[70] bg-rose-900/95 text-white flex items-center justify-center p-4 text-center">
        <div>
            <svg class="w-16 h-16 mx-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
            <h3 class="text-2xl font-bold mt-4">Exam Terminated</h3>
            <p class="mt-2 opacity-90">You repeatedly left the exam window. Your test has been submitted and marked as <b>FAIL</b>.</p>
            <p class="text-sm opacity-70 mt-2">Submitting…</p>
        </div>
    </div>

    {{-- Next-section confirmation --}}
    <div id="sectionModal" class="hidden fixed inset-0 z-[60] bg-black/50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6">
            <div class="w-14 h-14 mx-auto rounded-full bg-violet-100 flex items-center justify-center mb-3">
                <svg class="w-8 h-8 text-violet-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </div>
            <h3 class="text-xl font-bold text-center text-slate-900">Move to the next section?</h3>
            <p class="text-slate-500 text-center mt-1 text-sm" id="sectionModalText"></p>
            <div id="sectionUnanswered" class="hidden mt-3 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800"></div>
            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeSectionModal()" class="flex-1 border border-slate-300 text-slate-700 py-2.5 rounded-xl font-semibold hover:bg-slate-50">Stay here</button>
                <button type="button" id="sectionConfirmBtn" onclick="confirmNextSection()" class="flex-1 bg-brand hover:bg-brand-dark text-white py-2.5 rounded-xl font-semibold">Next section →</button>
            </div>
        </div>
    </div>

    {{-- Submit confirmation (warns about unattempted questions) --}}
    <div id="submitModal" class="hidden fixed inset-0 z-[60] bg-black/50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6">
            <div class="w-14 h-14 mx-auto rounded-full bg-blue-100 flex items-center justify-center mb-3">
                <svg class="w-8 h-8 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 12h6M9 16h6M6 3h9l5 5v13H6z"/></svg>
            </div>
            <h3 class="text-xl font-bold text-center text-slate-900">Submit your exam?</h3>
            <p class="text-slate-500 text-center mt-1 text-sm">You cannot change your answers after submitting.</p>
            <div class="mt-3 text-center text-sm font-semibold text-slate-700" id="submitCount"></div>
            <div id="submitUnanswered" class="hidden mt-3 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                <div class="flex items-start gap-2">
                    <svg class="w-5 h-5 shrink-0 text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
                    <span id="submitUnansweredText"></span>
                </div>
            </div>
            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeSubmitModal()" class="flex-1 border border-slate-300 text-slate-700 py-2.5 rounded-xl font-semibold hover:bg-slate-50">Go back &amp; review</button>
                <button type="button" id="submitConfirmBtn" onclick="doSubmit(false,false)" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white py-2.5 rounded-xl font-semibold">Submit</button>
            </div>
        </div>
    </div>

    {{-- ===== Body ===== --}}
    <main class="flex-1 min-h-0 max-w-7xl w-full mx-auto px-4 sm:px-6 py-4 grid grid-cols-1 lg:grid-cols-3 gap-5 overflow-y-auto lg:overflow-hidden">
        {{-- Question area --}}
        <section class="lg:col-span-2 lg:min-h-0 flex flex-col">
            @foreach ($paper as $i => $item)
                @php
                    $q = $item['question']; $ans = $answers->get($q->id); $sel = $ans?->selected_option_ids ?? [];
                    $si = $hasSections ? ($qSec[$q->id] ?? 0) : null;
                    $sec = $hasSections ? $sections[$si] : null;
                    $pos = $hasSections ? ($qPos[$q->id] ?? 0) : $i;
                    $secCount = $hasSections ? count($sec['qids']) : count($paper);
                    $marks = $hasSections ? $sec['mpq'] : $q->marks;
                    $isFirst = $hasSections ? $pos === 0 : $i === 0;
                    $isLastInSec = $hasSections ? $pos === $secCount - 1 : $i === count($paper) - 1;
                    $isLastSection = ! $hasSections || $si === count($sections) - 1;
                @endphp
                <div class="qpane bg-white rounded-2xl shadow-sm border border-slate-100 flex-col lg:flex-1 lg:min-h-0 {{ $i === $startIndex ? 'flex' : 'hidden' }}"
                     id="q-{{ $q->id }}" data-qid="{{ $q->id }}" data-index="{{ $i }}" data-section="{{ $si ?? '' }}">
                    {{-- card header --}}
                    <div class="flex items-center justify-between gap-3 px-4 sm:px-6 pt-4 sm:pt-5 pb-3 border-b border-slate-100 shrink-0">
                        <div class="min-w-0">
                            @if ($hasSections)
                                <div class="text-[11px] font-bold text-brand uppercase tracking-wide truncate">Section: {{ $sec['title'] }}</div>
                            @endif
                            <h2 class="text-base font-bold text-slate-800">Question <span class="text-brand">{{ $pos + 1 }}</span> of {{ $secCount }}@if($hasSections) <span class="text-xs font-normal text-slate-400">in this section</span>@endif</h2>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg" style="background:rgb(var(--brand-rgb)/.1);color:rgb(var(--brand-rgb))">{{ $marks }} mark{{ $marks == 1 ? '' : 's' }}@if($hasSections && $sec['neg'] > 0) · −{{ \App\Services\SectionService::fmt($sec['neg']) }}@endif</span>
                            <button type="button" onclick="toggleReview({{ $q->id }})"
                                    class="review-btn flex items-center gap-1.5 text-sm font-medium border border-slate-200 rounded-lg px-3 py-1.5 hover:bg-slate-50 text-slate-600">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h12a1 1 0 0 1 1 1v17l-7-4-7 4V4a1 1 0 0 1 1-1z"/></svg>
                                <span class="review-label hidden sm:inline">Mark for review</span>
                            </button>
                            <input type="checkbox" class="review-flag hidden" @checked($ans?->marked_for_review)>
                        </div>
                    </div>

                    {{-- scrollable question + options --}}
                    <div class="px-4 sm:px-6 py-5 lg:flex-1 lg:min-h-0 lg:overflow-y-auto">
                        <p class="text-lg font-semibold text-slate-900 mb-4 leading-relaxed">{{ $q->text }}</p>
                        @if ($q->image_path)
                            <img src="{{ $q->image_path }}" alt="Question figure" class="max-w-full max-h-72 rounded-xl border border-slate-200 mb-5 bg-white">
                        @endif

                        @if (in_array($q->type, ['MCQ_SINGLE','MCQ_MULTI']))
                            <div class="space-y-3">
                                @foreach ($item['options'] as $oi => $opt)
                                    <label class="flex items-center gap-3 border rounded-xl px-4 py-3.5 cursor-pointer transition border-slate-200 hover:border-brand/50 has-[:checked]:border-brand has-[:checked]:bg-blue-50">
                                        <input type="{{ $q->type==='MCQ_MULTI'?'checkbox':'radio' }}" name="q{{ $q->id }}" value="{{ $opt->id }}"
                                               @checked(in_array($opt->id, $sel)) onchange="onAnswer({{ $q->id }})"
                                               class="answer-input w-5 h-5 shrink-0" style="accent-color:rgb(var(--brand-rgb))">
                                        <span class="text-slate-700"><span class="font-bold mr-1">{{ chr(65+$oi) }}.</span>{{ $opt->text }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @elseif ($q->type === 'TRUE_FALSE')
                            <div class="space-y-3">
                                @foreach (['1'=>'True','0'=>'False'] as $v=>$lbl)
                                    <label class="flex items-center gap-3 border rounded-xl px-4 py-3.5 cursor-pointer transition border-slate-200 hover:border-brand/50 has-[:checked]:border-brand has-[:checked]:bg-blue-50">
                                        <input type="radio" name="q{{ $q->id }}" value="{{ $v }}"
                                               @checked($ans && $ans->bool_answer === ($v == 1)) onchange="onAnswer({{ $q->id }})"
                                               class="answer-input w-5 h-5 shrink-0" style="accent-color:rgb(var(--brand-rgb))">
                                        <span class="text-slate-700 font-medium">{{ $lbl }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @elseif ($q->type === 'NUMERIC')
                            <input type="number" step="any" name="q{{ $q->id }}" value="{{ $ans?->numeric_answer }}" placeholder="Enter your numeric answer"
                                   oninput="onAnswerDebounced({{ $q->id }})" class="answer-input w-full max-w-xs rounded-xl border border-slate-300 px-4 py-3 focus:ring-2 focus:ring-brand outline-none">
                        @elseif ($q->type === 'FILL_BLANK')
                            <input type="text" name="q{{ $q->id }}" value="{{ $ans?->text_answer }}" placeholder="Type your answer"
                                   oninput="onAnswerDebounced({{ $q->id }})" class="answer-input w-full rounded-xl border border-slate-300 px-4 py-3 focus:ring-2 focus:ring-brand outline-none">
                        @else
                            <textarea name="q{{ $q->id }}" rows="6" placeholder="Write your answer here…"
                                      oninput="onAnswerDebounced({{ $q->id }})" class="answer-input w-full rounded-xl border border-slate-300 px-4 py-3 focus:ring-2 focus:ring-brand outline-none">{{ $ans?->text_answer }}</textarea>
                        @endif
                    </div>

                    {{-- nav (always visible) --}}
                    <div class="flex items-center justify-between gap-2 px-4 sm:px-6 py-4 border-t border-slate-100 shrink-0 bg-white rounded-b-2xl">
                        <button type="button" onclick="go({{ $i - 1 }})" {{ $isFirst ? 'disabled' : '' }}
                                class="inline-flex items-center gap-2 border-2 border-slate-200 rounded-xl px-4 sm:px-5 py-2.5 font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span>←</span> <span class="hidden sm:inline">Previous</span>
                        </button>
                        @if ($isLastInSec && $isLastSection)
                            <button type="button" onclick="submitExam(false)"
                                    class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl px-5 sm:px-6 py-2.5 font-semibold shadow-lg">Submit Test <span>→</span></button>
                        @elseif ($isLastInSec)
                            <button type="button" onclick="nextSection({{ $si }})"
                                    class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white rounded-xl px-5 sm:px-6 py-2.5 font-semibold shadow-lg">Next Section <span>→</span></button>
                        @else
                            <button type="button" onclick="go({{ $i + 1 }})"
                                    class="inline-flex items-center gap-2 bg-brand hover:bg-brand-dark text-white rounded-xl px-5 sm:px-6 py-2.5 font-semibold shadow-lg" style="box-shadow:0 10px 20px -8px rgb(var(--brand-rgb)/.6)"><span class="hidden sm:inline">Next Question</span><span class="sm:hidden">Next</span> <span>→</span></button>
                        @endif
                    </div>
                </div>
            @endforeach
        </section>

        {{-- Sidebar --}}
        <aside class="lg:col-span-1 lg:min-h-0 lg:overflow-y-auto space-y-4 pb-1">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 text-center">
                <div class="flex items-center justify-center gap-2 text-slate-500 text-sm mb-0.5">
                    <svg class="w-5 h-5 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    {{ $secTimer ? 'Section Time Remaining' : 'Time Remaining' }}
                </div>
                <div class="text-4xl font-extrabold text-brand font-mono tracking-tight" id="timer">--:--</div>
                <div class="flex justify-center gap-6 text-[11px] text-slate-400 mt-0.5"><span>Hours</span><span>Minutes</span><span>Seconds</span></div>
                @if ($secTimer)
                    <div class="text-xs text-slate-400 mt-2 border-t border-slate-100 pt-2">Whole exam: <span class="font-mono font-semibold text-slate-600" id="timerOverall">--:--</span> · when section time ends it locks and the next one opens</div>
                @endif
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
                <div class="flex items-center justify-between text-sm mb-2">
                    <span class="font-semibold text-slate-700">Progress</span>
                    <span class="text-slate-500"><span id="answeredCount">0</span> / {{ count($paper) }} Questions</span>
                </div>
                <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden">
                    <div id="progressBar" class="h-full bg-brand rounded-full transition-all" style="width:0%"></div>
                </div>
                @if ($hasSections)
                    <div class="text-xs text-slate-500 mt-2">Current section: <b class="text-slate-700" id="curSecName"></b> · <span id="curSecProgress"></span></div>
                @endif
            </div>

            {{-- Mobile: collapsible navigator; desktop: always open --}}
            <button type="button" onclick="document.getElementById('paletteWrap').classList.toggle('hidden')" class="lg:hidden w-full bg-white border border-slate-200 rounded-2xl px-4 py-3 text-sm font-semibold text-slate-700 flex items-center justify-between">
                <span>Question Navigator</span><span class="text-slate-400">▾</span>
            </button>
            <div id="paletteWrap" class="hidden lg:block bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="font-semibold text-slate-700">Question Palette</span>
                    <span class="text-xs text-slate-400">{{ count($paper) }} questions</span>
                </div>
                <div class="mb-3 max-h-72 lg:max-h-80 overflow-y-auto pr-1">
                    @if ($hasSections)
                        @foreach ($sections as $si => $s)
                            <div class="mb-3 last:mb-0" id="palsec-{{ $si }}">
                                <div class="flex items-center justify-between text-[11px] font-bold uppercase tracking-wide mb-1.5 {{ $s['accessible'] ? 'text-slate-600' : 'text-slate-400' }}">
                                    <span class="truncate">{{ $s['title'] }}</span>
                                    <span class="font-normal normal-case">@if($s['locked'])🔒 locked @elseif(!$s['accessible'])not yet open @else<span id="palcount-{{ $si }}"></span>@endif</span>
                                </div>
                                <div class="grid grid-cols-6 gap-1.5">
                                    @foreach ($s['qids'] as $pi => $qid)
                                        @php $gi = $qIndex[$qid] ?? null; @endphp
                                        @if ($gi !== null)
                                            <button type="button" onclick="go({{ $gi }})" id="pal-{{ $gi }}" {{ $s['accessible'] ? '' : 'disabled' }}
                                                    class="pal-btn aspect-square rounded-md text-xs font-semibold border border-slate-200 text-slate-600 hover:bg-slate-50">{{ $pi + 1 }}</button>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="grid grid-cols-6 gap-1.5">
                            @foreach ($paper as $i => $item)
                                <button type="button" onclick="go({{ $i }})" id="pal-{{ $i }}"
                                        class="pal-btn aspect-square rounded-md text-xs font-semibold border border-slate-200 text-slate-600 hover:bg-slate-50">{{ $i + 1 }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="grid grid-cols-2 gap-y-1.5 text-xs text-slate-500">
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-emerald-500 inline-block"></span> Answered</div>
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-brand inline-block"></span> Current</div>
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-slate-200 inline-block"></span> Not Attempted</div>
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-amber-400 inline-block"></span> For Review</div>
                </div>
            </div>

            <div class="bg-rose-50 border border-rose-200 rounded-2xl p-4 flex items-start gap-3">
                <svg class="w-6 h-6 text-rose-500 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
                <div class="text-sm">
                    <div class="font-bold text-rose-700">Important!</div>
                    <p class="text-slate-600 mt-0.5 leading-snug">Do not switch tabs or leave this page. Leaving is logged and may terminate your exam and mark you as <span class="font-bold text-rose-600">FAIL.</span></p>
                </div>
            </div>

            <button type="button" onclick="submitExam(false)" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-xl shadow-lg">Submit Exam</button>
        </aside>
    </main>

    <footer class="shrink-0 max-w-7xl mx-auto w-full px-6 py-2.5 flex items-center justify-between text-[11px] text-slate-400 border-t border-slate-200">
        <span class="flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z"/><path d="M9 12l2 2 4-4"/></svg>
            <span class="hidden sm:inline">Secure Assessment Environment · monitored</span>
        </span>
        <span>© {{ date('Y') }} PinTek Digital. ExamNex is a product of PinTek Digital. All rights reserved.</span>
    </footer>
</div>

<script>
// If this page is restored from the back/forward (bfcache) after the exam was
// submitted, force a fresh server load so the user lands on the result page
// instead of re-entering a finished exam.
window.addEventListener('pageshow', function (e) { if (e.persisted) location.reload(); });
const CSRF = document.querySelector('meta[name=csrf-token]').content;
const SAVE_URL = `{{ url('candidate/attempt/'.$attempt->id.'/save') }}`;
const EVENT_URL = `{{ route('attempt.event', $attempt->id) }}`;
const SUBMIT_URL = `{{ route('attempt.submit', $attempt->id) }}`;
const SECTION_URL = `{{ route('attempt.section', $attempt->id) }}`;
const QIDS = [{{ collect($paper)->map(fn($p)=>$p['question']->id)->implode(',') }}];
const TOTAL = QIDS.length;
// ---- Sections (empty for plain tests) ----
const SECTIONS = {!! $sectionsJson !!};
const QSEC = {!! $qsecJson !!};   // global index -> section index
const SERVER_NAV = {{ $serverNav ? 'true' : 'false' }};       // next section must be confirmed with the server
const LOCKS_ON_ADVANCE = {{ $locksOnAdvance ? 'true' : 'false' }};
const SECTION_TIMER = {{ $secTimer ? 'true' : 'false' }};
let current = {{ (int) $startIndex }};
let deadline = Date.now() + {{ (int) $remainingMs }};
let sectionDeadline = {{ $sectionRemainingMs !== null ? 'Date.now() + '.(int) $sectionRemainingMs : 'null' }};

function secOf(i) { return SECTIONS.length ? QSEC[i] : 0; }
function firstIndexOfSection(si) { return QSEC.indexOf(si); }
function go(i) {
    if (i < 0 || i >= TOTAL) return;
    if (SECTIONS.length && !SECTIONS[secOf(i)].accessible) return;   // locked / not yet open
    current = i;
    document.querySelectorAll('.qpane').forEach(p => { p.classList.add('hidden'); p.classList.remove('flex'); });
    const pane = document.getElementById('q-' + QIDS[i]);
    pane.classList.remove('hidden'); pane.classList.add('flex');
    paintPalette();
    if (window.innerWidth < 1024) window.scrollTo({ top: 0 });
}
function goSection(si) { if (!SECTIONS[si] || !SECTIONS[si].accessible) return; const f = firstIndexOfSection(si); if (f >= 0) go(f); }

function collect(qid) {
    const card = document.getElementById('q-' + qid);
    const inputs = card.querySelectorAll('.answer-input');
    const review = card.querySelector('.review-flag').checked;
    const p = { question_id: qid, marked_for_review: review, selected_option_ids: null, text_answer: null, numeric_answer: null, bool_answer: null };
    const first = inputs[0];
    if (!first) return p;
    if (first.type === 'radio' || first.type === 'checkbox') {
        const checked = [...inputs].filter(i => i.checked);
        if (checked.length && (checked[0].value === '1' || checked[0].value === '0') && inputs.length === 2) p.bool_answer = checked[0].value === '1';
        else p.selected_option_ids = checked.map(i => parseInt(i.value));
    } else if (first.type === 'number') { p.numeric_answer = first.value === '' ? null : parseFloat(first.value); }
    else { p.text_answer = first.value; }
    return p;
}
function isAnswered(qid) {
    const p = collect(qid);
    return (p.selected_option_ids && p.selected_option_ids.length) || (p.text_answer && p.text_answer.trim()) || p.numeric_answer !== null || p.bool_answer !== null;
}
async function save(qid) { try { await fetch(SAVE_URL, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(collect(qid)) }); } catch (_) {} }
function onAnswer(qid) { save(qid); paintPalette(); updateProgress(); }
const timers = {};
function onAnswerDebounced(qid) { clearTimeout(timers[qid]); timers[qid] = setTimeout(() => save(qid), 600); paintPalette(); updateProgress(); }

function toggleReview(qid) {
    const card = document.getElementById('q-' + qid);
    const flag = card.querySelector('.review-flag'); flag.checked = !flag.checked;
    const btn = card.querySelector('.review-btn'), label = card.querySelector('.review-label');
    if (flag.checked) { btn.classList.add('bg-amber-50','border-amber-300','text-amber-700'); label.textContent = 'Marked'; }
    else { btn.classList.remove('bg-amber-50','border-amber-300','text-amber-700'); label.textContent = 'Mark for review'; }
    save(qid); paintPalette();
}

function paintPalette() {
    QIDS.forEach((qid, i) => {
        const b = document.getElementById('pal-' + i);
        if (!b) return;
        const marked = document.getElementById('q-' + qid).querySelector('.review-flag').checked;
        const open = !SECTIONS.length || SECTIONS[secOf(i)].accessible;
        b.className = 'pal-btn aspect-square rounded-md text-xs font-semibold border transition' + (open ? '' : ' opacity-40 cursor-not-allowed');
        if (i === current) b.classList.add('bg-brand','text-white','border-brand');
        else if (marked) b.classList.add('bg-amber-400','text-white','border-amber-400');
        else if (isAnswered(qid)) b.classList.add('bg-emerald-500','text-white','border-emerald-500');
        else b.classList.add('border-slate-200','text-slate-600','hover:bg-slate-50');
    });
    // section tabs + per-section counters
    SECTIONS.forEach((s, si) => {
        const tab = document.getElementById('tab-' + si);
        const answered = QIDS.filter((q, i) => QSEC[i] === si && isAnswered(q)).length;
        const cnt = document.getElementById('tabcount-' + si); if (cnt) cnt.textContent = answered + '/' + s.count;
        const pc = document.getElementById('palcount-' + si); if (pc) pc.textContent = answered + ' / ' + s.count + ' answered';
        if (!tab) return;
        tab.classList.remove('bg-brand','text-white','border-brand','bg-white','text-slate-600','border-slate-200');
        if (si === secOf(current)) tab.classList.add('bg-brand','text-white','border-brand');
        else tab.classList.add('bg-white','text-slate-600','border-slate-200');
        if (si === secOf(current)) {
            const n = document.getElementById('curSecName'), p = document.getElementById('curSecProgress');
            if (n) n.textContent = s.title;
            if (p) p.textContent = answered + ' / ' + s.count + ' answered';
        }
    });
}
function updateProgress() {
    const n = QIDS.filter(isAnswered).length;
    document.getElementById('answeredCount').textContent = n;
    document.getElementById('progressBar').style.width = (TOTAL ? (n / TOTAL * 100) : 0) + '%';
}

// ---- Section flow ----
let pendingSection = null;
function nextSection(si) {
    if (!SECTIONS.length || si >= SECTIONS.length - 1) return;
    const unanswered = QIDS.map((q, i) => ({ q, i })).filter(o => QSEC[o.i] === si && !isAnswered(o.q));
    if (!SERVER_NAV) {                      // free navigation: just jump, nothing is locked
        go(firstIndexOfSection(si + 1));
        return;
    }
    pendingSection = si;
    const box = document.getElementById('sectionUnanswered');
    document.getElementById('sectionModalText').textContent = LOCKS_ON_ADVANCE
        ? 'You will not be able to come back to "' + SECTIONS[si].title + '" once you move on.'
        : 'You are about to open "' + SECTIONS[si + 1].title + '".';
    if (unanswered.length) {
        box.classList.remove('hidden');
        box.innerHTML = '⚠ <b>' + unanswered.length + ' question' + (unanswered.length > 1 ? 's' : '') + ' not attempted</b> in this section (Q ' +
            unanswered.map(o => QSEC.slice(0, o.i).filter(x => x === si).length + 1).join(', ') + ').';
    } else { box.classList.add('hidden'); }
    document.getElementById('sectionModal').classList.remove('hidden');
}
function closeSectionModal() { document.getElementById('sectionModal').classList.add('hidden'); pendingSection = null; }
function confirmNextSection() { postSection(); }
function postSection() {
    if (submitting) return;
    submitting = true; window.onbeforeunload = null;
    const f = document.createElement('form'); f.method = 'POST'; f.action = SECTION_URL;
    f.innerHTML = `<input type="hidden" name="_token" value="${CSRF}"><input type="hidden" name="action" value="next">`;
    document.body.appendChild(f); f.submit();
}

// ---- Clock (server-authoritative deadlines rendered client-side) ----
function fmtMs(ms) {
    const s = Math.max(0, Math.floor(ms / 1000)), h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
    const pad = x => String(x).padStart(2, '0');
    return (h > 0 ? pad(h) + ':' : '') + pad(m) + ':' + pad(sec);
}
function tick() {
    const ms = deadline - Date.now();
    const t = document.getElementById('timer'), mini = document.getElementById('timerMini'), ov = document.getElementById('timerOverall');
    if (ms <= 0) { t.textContent = '00:00'; if (mini) mini.textContent = '00:00'; submitExam(true); return; }
    let shown = ms;
    if (SECTION_TIMER && sectionDeadline !== null) {
        const sms = sectionDeadline - Date.now();
        if (ov) ov.textContent = fmtMs(ms);
        if (sms <= 0) { t.textContent = '00:00'; if (mini) mini.textContent = '00:00'; postSection(); return; }   // server locks & advances
        shown = sms;
    }
    t.textContent = fmtMs(shown); if (mini) mini.textContent = fmtMs(shown);
    if (shown < 60000) { t.classList.remove('text-brand'); t.classList.add('text-rose-500'); if (mini) { mini.classList.remove('text-brand'); mini.classList.add('text-rose-500'); } }
}
setInterval(tick, 1000); tick();

// ===== Anti-cheat (per-test settings) =====
const SEC = {
    tab:   {{ $test->prevent_tab_switch ? 'true' : 'false' }},
    copy:  {{ $test->detect_copy ? 'true' : 'false' }},
    right: {{ $test->restrict_right_click ? 'true' : 'false' }},
    full:  {{ $test->full_screen ? 'true' : 'false' }},
    warn:  {{ $test->show_warning ? 'true' : 'false' }},
};
const MAX_WARNINGS = SEC.warn ? 1 : 0;   // with warnings: 1 warning then terminate; without: terminate on first
let violations = 0;
let terminated = false;

function logViolation() {
    // Send the CSRF token IN THE BODY so the beacon passes VerifyCsrfToken
    // (sendBeacon cannot set headers). Otherwise the POST 419s and the server
    // never records the violation.
    try {
        if (navigator.sendBeacon) {
            const body = new Blob(['_token=' + encodeURIComponent(CSRF)], { type: 'application/x-www-form-urlencoded' });
            navigator.sendBeacon(EVENT_URL, body);
        } else {
            fetch(EVENT_URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF }, keepalive: true });
        }
    } catch (_) {}
}
function handleViolation(reason) {
    if (terminated || submitting) return;
    violations++;
    logViolation();
    if (violations > MAX_WARNINGS) { terminate(); return; }
    document.getElementById('cheatMsg').textContent = reason;
    document.getElementById('cheatCount').textContent = 'Next time your exam will be terminated and marked as FAIL.';
    document.getElementById('cheatModal').classList.remove('hidden');
}
function dismissCheat() { document.getElementById('cheatModal').classList.add('hidden'); }

// Tab switch / minimize / app switch / new tab -> violation (if enabled)
if (SEC.tab) {
    document.addEventListener('visibilitychange', () => { if (document.hidden) handleViolation('You switched tabs, opened a new tab, or minimized the exam. This is not allowed.'); });
}

// Block copy / cut / paste / drag / selection (except answer fields) - if enabled
if (SEC.copy) {
    ['copy','cut','paste','dragstart','selectstart'].forEach(ev =>
        document.addEventListener(ev, e => { const t = e.target; if (!(t && t.matches && t.matches('input, textarea'))) e.preventDefault(); }));
}
// Right-click / inspect - if enabled
if (SEC.right) {
    document.addEventListener('contextmenu', e => { const t = e.target; if (!(t && t.matches && t.matches('input, textarea'))) e.preventDefault(); });
}
// Keyboard shortcuts (copy/print/save/view-source/devtools) + screenshot deter
document.addEventListener('keydown', e => {
    const k = (e.key || '').toLowerCase();
    const inField = e.target && e.target.matches && e.target.matches('input, textarea');
    if (SEC.copy && (e.ctrlKey || e.metaKey) && ['c','x','p','s','u'].includes(k) && !inField) e.preventDefault();
    if (SEC.right && (e.key === 'F12' || ((e.ctrlKey || e.metaKey) && e.shiftKey && ['i','j','c'].includes(k)))) e.preventDefault();
    if (SEC.copy && e.key === 'PrintScreen') { try { navigator.clipboard && navigator.clipboard.writeText('Screenshots are not allowed.'); } catch (_) {} deterScreenshot(); }
});

// Full-screen mode (best-effort)
if (SEC.full) {
    const goFull = () => { const el = document.documentElement; if (el.requestFullscreen) el.requestFullscreen().catch(()=>{}); document.removeEventListener('click', goFull); };
    document.addEventListener('click', goFull, { once: true });
}
function deterScreenshot() {
    logViolation();
    const w = document.getElementById('warn'); w.textContent = '⚠ Screenshots are not allowed and have been logged.'; w.classList.remove('hidden');
    setTimeout(() => w.classList.add('hidden'), 3000);
}

function terminate() {
    terminated = true;
    document.getElementById('cheatModal').classList.add('hidden');
    document.getElementById('termOverlay').classList.remove('hidden');
    setTimeout(() => submitExam(true, true), 1200);
}

window.addEventListener('beforeunload', e => { if (!submitting) { e.preventDefault(); e.returnValue = ''; } });

let submitting = false;
function submitExam(auto, term) {
    if (submitting) return;
    // Auto-submit (time up) and termination submit immediately, no dialog.
    if (auto) { doSubmit(true, term); return; }
    // Manual submit: warn about any unattempted questions before finishing.
    const unanswered = QIDS.map((qid, i) => ({ qid, i })).filter(o => !isAnswered(o.qid));
    const answered = TOTAL - unanswered.length;
    document.getElementById('submitCount').textContent = answered + ' of ' + TOTAL + ' questions answered';
    const box = document.getElementById('submitUnanswered');
    const btn = document.getElementById('submitConfirmBtn');
    if (unanswered.length) {
        box.classList.remove('hidden');
        let detail;
        if (SECTIONS.length) {
            detail = SECTIONS.map((s, si) => {
                const qs = unanswered.filter(o => QSEC[o.i] === si).map(o => QSEC.slice(0, o.i).filter(x => x === si).length + 1);
                return qs.length ? '<b>' + s.title + '</b>: Q ' + qs.join(', ') : null;
            }).filter(Boolean).join(' · ');
        } else {
            detail = 'Q ' + unanswered.map(o => o.i + 1).join(', ');
        }
        document.getElementById('submitUnansweredText').innerHTML =
            'You have <b>not attempted ' + unanswered.length + ' question' + (unanswered.length > 1 ? 's' : '') + '</b> (' + detail + '). They will be marked as unanswered.';
        btn.textContent = 'Submit anyway';
    } else {
        box.classList.add('hidden');
        btn.textContent = 'Submit';
    }
    document.getElementById('submitModal').classList.remove('hidden');
}
function closeSubmitModal() { document.getElementById('submitModal').classList.add('hidden'); }
function doSubmit(auto, term) {
    if (submitting) return;
    submitting = true; window.onbeforeunload = null;
    const f = document.createElement('form'); f.method = 'POST'; f.action = SUBMIT_URL;
    f.innerHTML = `<input type="hidden" name="_token" value="${CSRF}"><input type="hidden" name="auto" value="${auto ? 1 : 0}"><input type="hidden" name="terminated" value="${term ? 1 : 0}">`;
    document.body.appendChild(f); f.submit();
}

document.querySelectorAll('.qpane').forEach(card => {
    if (card.querySelector('.review-flag').checked) { card.querySelector('.review-btn').classList.add('bg-amber-50','border-amber-300','text-amber-700'); card.querySelector('.review-label').textContent = 'Marked'; }
});
paintPalette();
updateProgress();
</script>
</body>
</html>
