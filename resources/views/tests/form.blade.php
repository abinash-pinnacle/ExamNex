@extends('layouts.app')
@section('title', $test ? 'Edit Test' : 'Create Test')
@section('content')
<div class="lg:h-[calc(100%_-_4rem)] lg:flex lg:flex-col lg:min-h-0">
@php
    $editing = (bool) $test;
    $src = $test ?? ($prefill ?? null);
    $S = fn ($k, $d = null) => \App\Models\Setting::get($k, $d);
    // value helper
    $v = function ($f, $def = null) use ($src) { return old($f, $src->$f ?? $def); };
    // checkbox helper (new-test defaults matched to the design)
    $c = function ($f, $def = false) use ($src) {
        if (old() && request()->isMethod('post')) { return (bool) old($f); }
        return $src ? (bool) $src->$f : $def;
    };
    $qorder = old('question_order', $src->question_order ?? 'SHUFFLE');
    // Passing criteria (percentage of total, or fixed marks)
    $passPercent = old('passing_percent', $src->passing_percent ?? null);
    $passMarks = (int) old('passing_marks', $src->passing_marks ?? 0);
    $passMode = old('passing_mode', ($passPercent !== null) ? 'percent' : ($editing && !($src->passing_percent ?? null) ? 'marks' : 'percent'));
    $passPercentVal = $passPercent ?? (int) $S('default_passing_percent', 40);
@endphp

<div class="flex items-center gap-2 text-sm text-slate-500 mb-3 shrink-0">
    <a href="{{ route('tests.index') }}" class="hover:text-brand">Tests</a> <span>›</span>
    <span class="text-slate-700 font-medium">{{ $editing ? 'Edit Test' : 'Create Test' }}</span>
</div>
<div class="lg:flex-1 lg:min-h-0 lg:overflow-y-auto lg:pr-1">

<form method="POST" action="{{ $editing ? route('tests.update', $test) : route('tests.store') }}" id="testForm">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
            <span class="w-11 h-11 rounded-xl bg-blue-50 text-brand flex items-center justify-center">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2"/></svg>
            </span>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900">{{ $editing ? 'Edit Test' : 'Create New Test' }}</h1>
                <p class="text-slate-500 text-sm">Set up a secure, professional and flexible test in minutes.</p>
            </div>
        </div>
        <div class="flex gap-2">
            @unless ($editing)
                <button type="submit" formaction="{{ route('tests.template') }}" class="border border-slate-200 bg-white px-4 py-2.5 rounded-xl text-sm font-semibold text-slate-700 hover:bg-slate-50">💾 Save as Template</button>
            @endunless
            <button type="submit" class="bg-brand hover:bg-brand-dark text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow-lg">{{ $editing ? 'Save changes' : 'Create Test' }}</button>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- ================= LEFT ================= --}}
        <div class="xl:col-span-2 space-y-6">
            {{-- Basic Information --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <span class="w-9 h-9 rounded-lg bg-blue-50 text-brand flex items-center justify-center"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2"/></svg></span>
                        <div><div class="font-bold text-slate-800">Basic Information</div><div class="text-xs text-slate-400">Add the essential details about your test.</div></div>
                    </div>
                    @if (($templates ?? collect())->count())
                        <select onchange="if(this.value) location.href='{{ route('tests.create') }}?template='+this.value" class="text-sm rounded-lg border border-slate-200 px-3 py-1.5 text-slate-600">
                            <option value="">✨ Use Template</option>
                            @foreach ($templates as $t)<option value="{{ $t->id }}">{{ $t->title }}</option>@endforeach
                        </select>
                    @endif
                </div>

                <label class="block text-sm font-medium mb-1">Test Title <span class="text-rose-500">*</span></label>
                <input name="title" value="{{ $v('title') }}" required placeholder="e.g. AIML Placement Test" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 mb-4 focus:ring-2 focus:ring-brand outline-none">

                <label class="block text-sm font-medium mb-1">Description</label>
                <div class="border border-slate-300 rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-brand">
                    <div class="flex items-center gap-1 border-b border-slate-200 px-2 py-1.5 bg-slate-50 text-slate-500">
                        <button type="button" onclick="wrapDesc('**')" class="w-7 h-7 rounded hover:bg-slate-200 font-bold">B</button>
                        <button type="button" onclick="wrapDesc('*')" class="w-7 h-7 rounded hover:bg-slate-200 italic">I</button>
                        <button type="button" onclick="wrapDesc('__')" class="w-7 h-7 rounded hover:bg-slate-200 underline">U</button>
                        <button type="button" onclick="prefixDesc('- ')" class="w-7 h-7 rounded hover:bg-slate-200">•</button>
                        <button type="button" onclick="prefixDesc('1. ')" class="w-7 h-7 rounded hover:bg-slate-200 text-xs">1.</button>
                    </div>
                    <textarea name="description" id="descBox" maxlength="2000" rows="4" oninput="descCount()" placeholder="Enter a brief description about the test, instructions to candidates, syllabus, etc." class="w-full px-3 py-2.5 outline-none resize-y">{{ $v('description') }}</textarea>
                </div>
                <div class="text-xs text-slate-400 mt-1"><span id="descCount">0</span>/2000 characters</div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Subject <span class="text-rose-500">*</span></label>
                        <input name="subject" value="{{ $v('subject') }}" list="subjectList" placeholder="Select subject" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 focus:ring-2 focus:ring-brand outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Category</label>
                        <input name="category" value="{{ $v('category') }}" placeholder="Select category" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 focus:ring-2 focus:ring-brand outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Audience</label>
                        <input name="audience" value="{{ $v('audience') }}" placeholder="Select audience type" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 focus:ring-2 focus:ring-brand outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-4">
                    <div><label class="block text-sm font-medium mb-1">Duration (min) <span class="text-rose-500">*</span></label><input name="duration_minutes" id="pv_dur" type="number" min="1" value="{{ $v('duration_minutes', $S('default_duration', 60)) }}" oninput="syncPreview()" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 focus:ring-2 focus:ring-brand outline-none"></div>
                    <div><label class="block text-sm font-medium mb-1">Total Marks</label><input type="number" value="{{ $v('total_marks', $editing ? $test->total_marks : 0) }}" id="pv_marks" readonly title="Auto-calculated from the questions you add" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-500"></div>
                    <div><label class="block text-sm font-medium mb-1">Max Attempts</label><input name="max_attempts" id="pv_att" type="number" min="1" value="{{ $v('max_attempts', $S('default_max_attempts', 1)) }}" oninput="syncPreview()" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 focus:ring-2 focus:ring-brand outline-none"></div>
                </div>

                {{-- Passing Criteria: % of total (auto) or fixed marks --}}
                <div class="mt-4 rounded-xl border border-blue-100 bg-blue-50/50 p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-8 h-8 rounded-lg bg-brand/10 text-brand flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-bold text-slate-800 text-sm">Passing Criteria <span class="text-rose-500">*</span></div>
                            <div class="text-xs text-slate-400">The score a candidate needs to <b>PASS</b> — as a % of total (recommended) or fixed marks.</div>
                        </div>
                    </div>
                    <input type="hidden" name="passing_mode" id="passing_mode" value="{{ $passMode }}">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="inline-flex rounded-lg border border-slate-300 bg-white p-0.5 text-sm shrink-0" id="passModeToggle">
                            <button type="button" data-mode="percent" onclick="setPassMode('percent')" class="px-3 py-1.5 rounded-md font-medium text-slate-500 transition">% of total</button>
                            <button type="button" data-mode="marks" onclick="setPassMode('marks')" class="px-3 py-1.5 rounded-md font-medium text-slate-500 transition">Fixed marks</button>
                        </div>
                        <div id="passPercentWrap" class="relative w-28">
                            <input name="passing_percent" id="passing_percent" type="number" min="0" max="100" value="{{ $passPercentVal }}" oninput="syncPass()" class="w-full rounded-lg border border-slate-300 pl-3 pr-8 py-2 focus:ring-2 focus:ring-brand outline-none">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">%</span>
                        </div>
                        <div id="passMarksWrap" class="flex items-center gap-2">
                            <input name="passing_marks" id="passing_marks" type="number" min="0" value="{{ $passMarks }}" oninput="syncPass()" class="w-24 rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-brand outline-none">
                            <span class="text-xs text-slate-400">marks</span>
                        </div>
                    </div>
                    <div class="mt-2" id="passHint"></div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Result Visibility</label>
                        <select name="result_visibility" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 focus:ring-2 focus:ring-brand outline-none">
                            @foreach (['AFTER_REVIEW'=>'After Review','IMMEDIATE'=>'Immediate','HIDDEN'=>'Hidden'] as $k=>$lbl)
                                <option value="{{ $k }}" @selected($v('result_visibility', $S('default_result_visibility','AFTER_REVIEW'))===$k)>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Test Mode</label>
                        <select class="w-full rounded-xl border border-slate-300 px-3 py-2.5 bg-slate-50 text-slate-600"><option>Online (Browser)</option></select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Question Order</label>
                        <select name="question_order" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 focus:ring-2 focus:ring-brand outline-none">
                            <option value="SHUFFLE" @selected($qorder==='SHUFFLE')>Shuffle</option>
                            <option value="SEQUENTIAL" @selected($qorder==='SEQUENTIAL')>As added (sequential)</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Advanced Settings --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <div class="flex items-center gap-2 mb-4">
                    <span class="w-9 h-9 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8M4.6 9a1.6 1.6 0 0 0-.3-1.8"/></svg></span>
                    <div><div class="font-bold text-slate-800">Advanced Settings</div><div class="text-xs text-slate-400">Configure security, behavior and advanced options.</div></div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    {{-- Question Settings --}}
                    <div class="rounded-xl border border-slate-100 p-4">
                        <div class="font-semibold text-sm text-slate-700 mb-3">📋 Question Settings</div>
                        <div class="space-y-2.5 text-sm">
                            <x-check name="shuffle_hint" label="Shuffle questions" :checked="$qorder==='SHUFFLE'" disabled note="controlled by Question Order" />
                            <x-check name="shuffle_options" label="Shuffle options" :checked="$c('shuffle_options', true)" />
                            <x-check name="negative_marking_on" label="Negative marking" :checked="$c('negative_marking_on', false)" />
                            <input name="negative_marks" type="number" step="0.25" min="0" value="{{ $v('negative_marks', $editing ? $test->negative_marks : 0.25) }}" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm"> <span class="text-xs text-slate-400">marks per wrong answer</span>
                            <x-check name="one_per_page" label="Show one question per page" :checked="$c('one_per_page', true)" />
                            <x-check name="allow_review" label="Allow review before submission" :checked="$c('allow_review', false)" />
                            <x-check name="autosave" label="Auto-save answers (every 10 seconds)" :checked="$c('autosave', true)" />
                        </div>
                    </div>

                    {{-- Security & Proctoring --}}
                    <div class="rounded-xl border border-slate-100 p-4">
                        <div class="font-semibold text-sm text-slate-700 mb-3">🛡 Security & Proctoring</div>
                        <div class="space-y-2.5 text-sm">
                            <x-check name="full_screen" label="Full screen mode (recommended)" :checked="$c('full_screen', true)" />
                            <x-check name="prevent_tab_switch" label="Prevent tab switch / window change" :checked="$c('prevent_tab_switch', true)" />
                            <x-check name="detect_copy" label="Detect copy, paste and screenshots" :checked="$c('detect_copy', true)" />
                            <x-check name="show_warning" label="Show warning on suspicious activity" :checked="$c('show_warning', true)" />
                            <x-check name="restrict_right_click" label="Restrict right-click, select & inspect" :checked="$c('restrict_right_click', true)" />
                            <x-check name="webcam_proctoring" label="Use webcam proctoring (beta)" :checked="$c('webcam_proctoring', false)" />
                            <x-check name="browser_lockdown" label="Use browser lockdown (advanced)" :checked="$c('browser_lockdown', false)" />
                        </div>
                    </div>

                    {{-- Extra Features --}}
                    <div class="rounded-xl border border-slate-100 p-4">
                        <div class="font-semibold text-sm text-slate-700 mb-3">✨ Extra Features</div>
                        <div class="space-y-2.5 text-sm">
                            <x-check name="issue_certificate" label="Issue certificate after completion" :checked="$c('issue_certificate', false)" />
                            <x-check name="show_solution" label="Show detailed solution after test" :checked="$c('show_solution', false)" />
                            <x-check name="allow_download_result" label="Allow candidates to download result" :checked="$c('allow_download_result', false)" />
                            <x-check name="email_notification" label="Send email notification to candidates" :checked="$c('email_notification', false)" />
                            <x-check name="ai_cheating_detection" label="Enable AI-based cheating detection" :checked="$c('ai_cheating_detection', false)" pro="Pro" />
                            <x-check name="feedback_form" label="Enable feedback form" :checked="$c('feedback_form', false)" />
                        </div>
                    </div>
                </div>

                <div class="mt-5">
                    <label class="block text-sm font-medium mb-1">Additional Instructions (Optional)</label>
                    <textarea name="instructions" rows="3" placeholder="Enter important instructions for candidates. These will be shown before the test starts." class="w-full rounded-xl border border-slate-300 px-3 py-2.5 focus:ring-2 focus:ring-brand outline-none">{{ $v('instructions') }}</textarea>
                </div>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium mb-1">Custom completion message (Optional)</label><input name="completion_message" value="{{ $v('completion_message') }}" placeholder="Shown to candidate after submitting" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 focus:ring-2 focus:ring-brand outline-none"></div>
                    <div><label class="block text-sm font-medium mb-1">Redirect URL after submission (Optional)</label><input name="redirect_url" value="{{ $v('redirect_url') }}" placeholder="https://..." class="w-full rounded-xl border border-slate-300 px-3 py-2.5 focus:ring-2 focus:ring-brand outline-none"></div>
                </div>
            </div>
        </div>

        {{-- ================= RIGHT ================= --}}
        <div class="space-y-6">
            {{-- Preview --}}
            <div class="rounded-2xl p-5 text-white shadow-lg" style="background:linear-gradient(135deg,#1e3a8a,#0b1e42)">
                <div class="flex items-center justify-between mb-3">
                    <span class="font-bold flex items-center gap-2"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg> Test Preview</span>
                </div>
                <div class="bg-white text-slate-800 rounded-xl p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-lg bg-gradient-to-br from-blue-500 to-blue-700 text-white font-bold flex items-center justify-center" id="pv_initials">AI</div>
                        <div class="min-w-0">
                            <div class="font-bold truncate" id="pv_title">AIML Placement Test</div>
                            <div class="text-xs text-slate-400"><span id="pv_dur2">60</span> min · <span id="pv_pass2">0</span> pass · Attempts: <span id="pv_att2">1</span></div>
                        </div>
                    </div>
                    <p class="text-sm text-slate-500 mt-3" id="pv_desc">This is a sample placement test.</p>
                    <div class="flex flex-wrap gap-3 text-xs text-slate-500 mt-3">
                        <span>🖥 Mode: Online</span><span>🔁 Attempts: <span id="pv_att3">1</span></span><span>🎯 Pass: <span id="pv_pass3">0</span></span>
                    </div>
                </div>
            </div>

            {{-- Schedule --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
                <div class="font-bold text-slate-800 mb-3 flex items-center gap-2"><svg class="w-5 h-5 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg> Test Schedule</div>
                <div class="grid grid-cols-1 gap-3">
                    <div><label class="block text-xs font-medium text-slate-500 mb-1">Opens At (Optional)</label><input name="starts_at" type="datetime-local" value="{{ old('starts_at', optional($test?->starts_at)->format('Y-m-d\TH:i')) }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-brand outline-none"></div>
                    <div><label class="block text-xs font-medium text-slate-500 mb-1">Closes At (Optional)</label><input name="ends_at" type="datetime-local" value="{{ old('ends_at', optional($test?->ends_at)->format('Y-m-d\TH:i')) }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-brand outline-none"></div>
                    <div class="flex items-center gap-2">
                        <label class="text-sm text-slate-600 flex-1">Joining grace period</label>
                        <input name="grace_minutes" type="number" min="0" value="{{ $v('grace_minutes', 0) }}" class="w-20 rounded-lg border border-slate-300 px-2 py-1.5 text-sm"> <span class="text-xs text-slate-400">min</span>
                    </div>
                </div>
            </div>

            {{-- Candidates & Access --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
                <div class="font-bold text-slate-800 mb-3 flex items-center gap-2"><svg class="w-5 h-5 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H4v-2a4 4 0 0 1 3-3.87m6-1.13a4 4 0 1 0-4-4 4 4 0 0 0 4 4z"/></svg> Candidates & Access</div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Max Candidates (Public Link)</label>
                <input name="max_candidates" type="number" min="1" value="{{ $v('max_candidates', 200) }}" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 mb-3 text-sm focus:ring-2 focus:ring-brand outline-none">
                <div class="space-y-2 text-sm">
                    <x-check name="public_access" label="Generate public test link" :checked="$c('public_access', true)" />
                    <x-check name="require_registration" label="Require candidate registration" :checked="$c('require_registration', true)" />
                    <x-check name="password_protect" label="Password protect test" :checked="$c('access_password', false) ? true : false" onToggle="togglePw" />
                    <input name="access_password" id="pwField" value="{{ $editing && $test->access_password ? '' : '' }}" placeholder="Set a password" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm {{ ($src && $src->access_password) ? '' : 'hidden' }}">
                </div>
            </div>

            {{-- Quick tips --}}
            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 text-sm text-amber-800">
                <div class="font-bold mb-2">💡 Quick Tips</div>
                <ul class="list-disc list-inside space-y-1 text-amber-700/90">
                    <li>Use clear instructions for better engagement.</li>
                    <li>Enable proctoring for important exams.</li>
                    <li>Save as a template to reuse this setup.</li>
                    <li>Generate a public link for large-scale tests.</li>
                </ul>
            </div>
        </div>
    </div>
</form>

<datalist id="subjectList">
    @foreach (\App\Models\Subject::orderBy('name')->pluck('name')->unique() as $s)<option value="{{ $s }}">@endforeach
</datalist>

@push('scripts')
<script>
    const $ = id => document.getElementById(id);
    // ---- Passing criteria (% of total, or fixed marks) ----
    function passTotal(){ return parseInt($('pv_marks').value) || 0; }
    function effPassMarks(){
        const mode = $('passing_mode').value, total = passTotal();
        if (mode === 'percent') {
            const p = Math.min(100, Math.max(0, parseInt($('passing_percent').value) || 0));
            return Math.ceil(p / 100 * total);
        }
        return parseInt($('passing_marks').value) || 0;
    }
    function setPassMode(m){
        $('passing_mode').value = m;
        $('passPercentWrap').classList.toggle('hidden', m !== 'percent');
        $('passMarksWrap').classList.toggle('hidden', m !== 'marks');
        document.querySelectorAll('#passModeToggle [data-mode]').forEach(b => {
            const on = b.dataset.mode === m;
            b.classList.toggle('bg-brand', on);
            b.classList.toggle('text-white', on);
            b.classList.toggle('text-slate-500', !on);
        });
        syncPass();
    }
    function syncPass(){
        const total = passTotal(), pass = effPassMarks(), mode = $('passing_mode').value;
        const pct = total > 0 ? Math.round(pass / total * 100) : (parseInt($('passing_percent').value) || 0);
        const hint = $('passHint');
        if (mode === 'percent' && total === 0) {
            hint.innerHTML = 'Pass = <b>' + (parseInt($('passing_percent').value) || 0) + '%</b> of total — auto-applied once you add questions.';
            hint.className = 'text-sm text-slate-500';
        } else if (pass > total && total > 0) {
            hint.innerHTML = '⚠ Pass mark (' + pass + ') is more than total (' + total + '). Lower it.';
            hint.className = 'text-sm text-rose-600 font-medium';
        } else {
            hint.innerHTML = 'Candidates need <b>' + pass + '</b> / ' + total + ' marks to pass' + (total > 0 ? ' (' + pct + '%)' : '') + '.';
            hint.className = 'text-sm text-slate-600';
        }
        syncPreview();
    }
    function syncPreview() {
        const t = document.querySelector('[name=title]').value || 'Untitled Test';
        $('pv_title').textContent = t;
        $('pv_initials').textContent = (t.replace(/[^A-Za-z0-9]/g,'').slice(0,2) || 'EX').toUpperCase();
        const dur = $('pv_dur').value || 0, att = $('pv_att').value || 1, pass = effPassMarks();
        $('pv_dur2').textContent = dur; $('pv_pass2').textContent = pass; $('pv_att2').textContent = att;
        $('pv_att3').textContent = att; $('pv_pass3').textContent = pass;
        const d = $('descBox').value.trim(); $('pv_desc').textContent = d || 'No description added yet.';
    }
    document.querySelector('[name=title]').addEventListener('input', syncPreview);
    function descCount() { $('descCount').textContent = $('descBox').value.length; syncPreview(); }
    // description toolbar
    function wrapDesc(w){ const b=$('descBox'); const s=b.selectionStart,e=b.selectionEnd; const sel=b.value.slice(s,e)||'text'; b.value=b.value.slice(0,s)+w+sel+w+b.value.slice(e); descCount(); }
    function prefixDesc(p){ const b=$('descBox'); const s=b.selectionStart; b.value=b.value.slice(0,s)+'\n'+p+b.value.slice(s); descCount(); }
    // password toggle
    function togglePw(cb){ $('pwField').classList.toggle('hidden', !cb.checked); if(!cb.checked) $('pwField').value=''; }
    descCount(); setPassMode($('passing_mode').value);
</script>
@endpush
</div>
</div>
@endsection
