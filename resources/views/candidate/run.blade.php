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
    </style>
</head>
@php
    $appName = \App\Models\Setting::get('org_name', config('app.name'));
    $conductedBy = \App\Models\Setting::get('conducted_by');
    $conductedSub = \App\Models\Setting::get('conducted_by_sub');
    $subtitle = $test->description ?: ($test->subject ?: 'Online Assessment');
    $tag1 = $test->category ?: 'Assessment';
@endphp
<body class="h-full overflow-hidden bg-gradient-to-b from-[#eaf1fb] to-[#f4f8fd] text-slate-800 {{ $test->detect_copy ? 'no-copy' : '' }}">
<div class="app-shell flex flex-col">

    {{-- ===== Header ===== --}}
    <header class="bg-white border-b border-slate-100 shrink-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-3 sm:gap-4">
            <div class="flex items-center gap-3 shrink-0">
                <x-brand-logo size="w-11 h-11" icon="w-6 h-6" />
                <div class="leading-tight">
                    <div class="text-[11px] text-slate-400">Conducted by</div>
                    <div class="text-base font-extrabold text-slate-900 leading-none">{{ $conductedBy }}</div>
                    <div class="text-[11px] text-slate-400 mt-0.5">{{ $conductedSub }}</div>
                </div>
            </div>
            <div class="text-center min-w-0 hidden md:block">
                <div class="font-extrabold text-slate-900 truncate">{{ $test->title }}</div>
                <div class="text-xs text-slate-400 truncate">{{ $subtitle }}</div>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <span class="hidden lg:flex items-center gap-2 border border-slate-200 rounded-lg px-3 py-1.5 text-sm font-semibold text-slate-600">
                    <svg class="w-4 h-4 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21h18M6 21V9l6-4 6 4v12"/></svg>{{ $tag1 }}
                </span>
                <span class="hand hidden xl:block text-base text-blue-300 leading-none text-right">Focus<br>Solve<br>Succeed</span>
            </div>
        </div>
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

    {{-- ===== Body ===== --}}
    <main class="flex-1 min-h-0 max-w-7xl w-full mx-auto px-4 sm:px-6 py-4 grid grid-cols-1 lg:grid-cols-3 gap-5 overflow-y-auto lg:overflow-hidden">
        {{-- Question area --}}
        <section class="lg:col-span-2 lg:min-h-0 flex flex-col">
            @foreach ($paper as $i => $item)
                @php $q = $item['question']; $ans = $answers->get($q->id); $sel = $ans?->selected_option_ids ?? []; @endphp
                <div class="qpane bg-white rounded-2xl shadow-sm border border-slate-100 flex-col lg:flex-1 lg:min-h-0 {{ $i === 0 ? 'flex' : 'hidden' }}"
                     id="q-{{ $q->id }}" data-qid="{{ $q->id }}" data-index="{{ $i }}">
                    {{-- card header --}}
                    <div class="flex items-center justify-between gap-3 px-6 pt-5 pb-3 border-b border-slate-100 shrink-0">
                        <h2 class="text-base font-bold text-slate-800">Question <span class="text-brand">{{ $i + 1 }}</span> of {{ count($paper) }}</h2>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg" style="background:rgb(var(--brand-rgb)/.1);color:rgb(var(--brand-rgb))">{{ $q->marks }} mark</span>
                            <button type="button" onclick="toggleReview({{ $q->id }})"
                                    class="review-btn flex items-center gap-1.5 text-sm font-medium border border-slate-200 rounded-lg px-3 py-1.5 hover:bg-slate-50 text-slate-600">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h12a1 1 0 0 1 1 1v17l-7-4-7 4V4a1 1 0 0 1 1-1z"/></svg>
                                <span class="review-label">Mark for review</span>
                            </button>
                            <input type="checkbox" class="review-flag hidden" @checked($ans?->marked_for_review)>
                        </div>
                    </div>

                    {{-- scrollable question + options --}}
                    <div class="px-6 py-5 lg:flex-1 lg:min-h-0 lg:overflow-y-auto">
                        <p class="text-lg font-semibold text-slate-900 mb-5 leading-relaxed">{{ $q->text }}</p>

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
                                               @checked($ans && $ans->bool_answer === ($v==='1')) onchange="onAnswer({{ $q->id }})"
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
                    <div class="flex items-center justify-between px-6 py-4 border-t border-slate-100 shrink-0 bg-white rounded-b-2xl">
                        <button type="button" onclick="go({{ $i - 1 }})" {{ $i === 0 ? 'disabled' : '' }}
                                class="inline-flex items-center gap-2 border-2 border-slate-200 rounded-xl px-5 py-2.5 font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span>←</span> Previous
                        </button>
                        @if ($i === count($paper) - 1)
                            <button type="button" onclick="submitExam(false)"
                                    class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl px-6 py-2.5 font-semibold shadow-lg">Submit Test <span>→</span></button>
                        @else
                            <button type="button" onclick="go({{ $i + 1 }})"
                                    class="inline-flex items-center gap-2 bg-brand hover:bg-brand-dark text-white rounded-xl px-6 py-2.5 font-semibold shadow-lg" style="box-shadow:0 10px 20px -8px rgb(var(--brand-rgb)/.6)">Next Question <span>→</span></button>
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
                    Time Remaining
                </div>
                <div class="text-4xl font-extrabold text-brand font-mono tracking-tight" id="timer">--:--</div>
                <div class="flex justify-center gap-6 text-[11px] text-slate-400 mt-0.5"><span>Hours</span><span>Minutes</span><span>Seconds</span></div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
                <div class="flex items-center justify-between text-sm mb-2">
                    <span class="font-semibold text-slate-700">Progress</span>
                    <span class="text-slate-500"><span id="answeredCount">0</span> / {{ count($paper) }} Questions</span>
                </div>
                <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden">
                    <div id="progressBar" class="h-full bg-brand rounded-full transition-all" style="width:0%"></div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="font-semibold text-slate-700">Question Palette</span>
                    <span class="text-xs text-slate-400">{{ count($paper) }} questions</span>
                </div>
                <div class="grid grid-cols-6 gap-1.5 mb-3 max-h-52 overflow-y-auto pr-1">
                    @foreach ($paper as $i => $item)
                        <button type="button" onclick="go({{ $i }})" id="pal-{{ $i }}"
                                class="pal-btn aspect-square rounded-md text-xs font-semibold border border-slate-200 text-slate-600 hover:bg-slate-50">{{ $i + 1 }}</button>
                    @endforeach
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
const CSRF = document.querySelector('meta[name=csrf-token]').content;
const SAVE_URL = `{{ url('candidate/attempt/'.$attempt->id.'/save') }}`;
const EVENT_URL = `{{ route('attempt.event', $attempt->id) }}`;
const SUBMIT_URL = `{{ route('attempt.submit', $attempt->id) }}`;
const QIDS = [{{ collect($paper)->map(fn($p)=>$p['question']->id)->implode(',') }}];
const TOTAL = QIDS.length;
let current = 0;
let deadline = Date.now() + {{ (int) $remainingMs }};

function go(i) {
    if (i < 0 || i >= TOTAL) return;
    current = i;
    document.querySelectorAll('.qpane').forEach(p => { p.classList.add('hidden'); p.classList.remove('flex'); });
    const pane = document.getElementById('q-' + QIDS[i]);
    pane.classList.remove('hidden'); pane.classList.add('flex');
    paintPalette();
}

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
        const marked = document.getElementById('q-' + qid).querySelector('.review-flag').checked;
        b.className = 'pal-btn aspect-square rounded-md text-xs font-semibold border transition';
        if (i === current) b.classList.add('bg-brand','text-white','border-brand');
        else if (marked) b.classList.add('bg-amber-400','text-white','border-amber-400');
        else if (isAnswered(qid)) b.classList.add('bg-emerald-500','text-white','border-emerald-500');
        else b.classList.add('border-slate-200','text-slate-600','hover:bg-slate-50');
    });
}
function updateProgress() {
    const n = QIDS.filter(isAnswered).length;
    document.getElementById('answeredCount').textContent = n;
    document.getElementById('progressBar').style.width = (TOTAL ? (n / TOTAL * 100) : 0) + '%';
}

function tick() {
    const ms = deadline - Date.now();
    const t = document.getElementById('timer');
    if (ms <= 0) { t.textContent = '00:00'; submitExam(true); return; }
    const s = Math.floor(ms / 1000), h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
    const pad = x => String(x).padStart(2, '0');
    t.textContent = (h > 0 ? pad(h) + ':' : '') + pad(m) + ':' + pad(sec);
    if (ms < 60000) { t.classList.remove('text-brand'); t.classList.add('text-rose-500'); }
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
    try { navigator.sendBeacon ? navigator.sendBeacon(EVENT_URL) : fetch(EVENT_URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF } }); } catch (_) {}
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
    if (!auto && !confirm('Submit your exam? You cannot change answers after this.')) return;
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
