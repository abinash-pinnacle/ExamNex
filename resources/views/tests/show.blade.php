@extends('layouts.app')
@section('title', $test->title)
@section('content')
@php
    $useSec = (bool) $test->use_sections;
    $fmt = fn ($n) => \App\Services\SectionService::fmt($n);
    $selSection = request('section') ? (int) request('section') : ($sections->first()?->id);
    $selObj = $sections->firstWhere('id', $selSection);
    $grouped = $test->testQuestions->groupBy(fn ($tq) => $tq->section_id ?? 0);
    $sumQ = $sections->sum('question_count');
    $sumM = $sections->sum(fn ($s) => $s->maxMarks());
    $durMin = $test->usesSections() ? \App\Services\SectionService::durationMinutes($test) : $test->duration_minutes;
    $qs = fn (array $extra = []) => route('tests.show', array_merge([$test], array_filter(array_merge($filters, ['section' => $selSection], $extra), fn ($v) => $v !== null && $v !== '')));
@endphp
<div class="lg:h-[calc(100%_-_4rem)] lg:flex lg:flex-col lg:min-h-0">
<div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 mb-5 shrink-0">
    <div class="min-w-0">
        <h1 class="text-2xl font-bold truncate">{{ $test->title }}</h1>
        <div class="flex flex-wrap items-center gap-2 mt-1 text-sm text-slate-500">
            <x-status :value="$test->status" />
            <span>{{ $test->total_marks }} marks · {{ $durMin }} min · overall pass {{ $test->passing_marks }}@if($useSec) · {{ $sections->count() }} section{{ $sections->count()===1?'':'s' }}@endif</span>
        </div>
    </div>
    <div class="flex gap-2 shrink-0">
        @if ($test->status !== 'PUBLISHED')
            <a href="{{ route('tests.edit', $test) }}" class="border border-slate-300 px-3 py-2 rounded-lg text-sm hover:bg-slate-50">Edit config</a>
            <form method="POST" action="{{ route('tests.publish', $test) }}">@csrf
                <button class="bg-emerald-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-emerald-700">Publish</button>
            </form>
        @else
            <a href="{{ route('tests.monitor', $test) }}" class="border border-slate-300 px-3 py-2 rounded-lg text-sm hover:bg-slate-50">Live monitor</a>
            <form method="POST" action="{{ route('tests.archive', $test) }}" onsubmit="return confirm('Archive this test?')">@csrf
                <button class="border border-slate-300 px-3 py-2 rounded-lg text-sm hover:bg-slate-50">Archive</button>
            </form>
        @endif
    </div>
</div>

<div class="lg:flex-1 lg:min-h-0 lg:overflow-y-auto lg:pr-1">
@if ($publicUrl)
    <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 mb-6">
        <div class="text-sm font-medium text-indigo-800 mb-1">Shared public link (students self-register)</div>
        <div class="flex gap-2">
            <input readonly value="{{ $publicUrl }}" class="flex-1 rounded-lg border-indigo-200 border px-3 py-2 text-sm bg-white" id="publicUrl">
            <button onclick="navigator.clipboard.writeText(document.getElementById('publicUrl').value)" class="bg-indigo-600 text-white px-4 rounded-lg text-sm">Copy</button>
        </div>
    </div>
@endif

@if ($useSec)
    {{-- ================= Section summary & readiness ================= --}}
    <div class="bg-white rounded-xl shadow-sm p-5 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
            <div class="font-semibold">Sections &amp; requirements</div>
            <div class="text-xs text-slate-500">
                {{ $test->section_navigation === 'SEQUENTIAL' ? 'Sequential navigation' : 'Free navigation' }} ·
                {{ $test->timer_mode === 'SECTION' ? 'Section-wise timer' : 'Overall timer' }}
                @if($test->section_navigation === 'SEQUENTIAL') · return to previous: {{ $test->allow_section_return ? 'allowed' : 'not allowed' }}@endif
            </div>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
            @foreach ([['Sections', $sections->count()], ['Total questions', $sumQ], ['Total marks', $sumM], ['Overall qualifying', $test->passing_marks ?: '—'], ['Duration', $durMin.' min']] as [$l, $v])
                <div class="rounded-xl bg-slate-50 border border-slate-100 p-3"><div class="text-xl font-extrabold text-slate-800">{{ $v }}</div><div class="text-[11px] text-slate-400">{{ $l }}</div></div>
            @endforeach
        </div>
        <div class="overflow-x-auto"><table class="w-full text-sm min-w-[600px]">
            <thead class="bg-slate-50 text-slate-500 text-left text-xs uppercase tracking-wide">
                <tr><th class="px-3 py-2">Section</th><th class="px-3 py-2">Required</th><th class="px-3 py-2">Available</th><th class="px-3 py-2">Marks</th><th class="px-3 py-2">Qualifying</th><th class="px-3 py-2">Neg.</th><th class="px-3 py-2">Time</th><th class="px-3 py-2">Status</th></tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($sections as $s)
                    @php $ok = $s->pool_count >= $s->question_count && $s->question_count > 0; @endphp
                    <tr>
                        <td class="px-3 py-2 font-semibold text-slate-800">{{ $s->title }}@if(!$s->is_mandatory) <span class="text-[10px] text-slate-400 font-normal">(optional)</span>@endif</td>
                        <td class="px-3 py-2">{{ $s->question_count }}</td>
                        <td class="px-3 py-2"><a href="{{ $qs(['section' => $s->id]) }}" class="text-brand hover:underline">{{ $s->pool_count }}</a></td>
                        <td class="px-3 py-2">{{ $s->maxMarks() }} <span class="text-slate-400 text-xs">({{ $s->marks_per_question }}/q)</span></td>
                        <td class="px-3 py-2">{{ $fmt($s->qualifying_marks) }}</td>
                        <td class="px-3 py-2">{{ $s->negative_marks > 0 ? '−'.$fmt($s->negative_marks) : '—' }}</td>
                        <td class="px-3 py-2">{{ $s->duration_minutes ? $s->duration_minutes.' min' : '—' }}</td>
                        <td class="px-3 py-2">
                            @if ($ok)<span class="text-emerald-600 font-medium">✓ Ready</span>
                            @else<span class="text-rose-600 font-medium">⚠ Need {{ max(0, $s->question_count - $s->pool_count) }} more</span>@endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
        @if ($sectionErrors)
            <div class="mt-3 rounded-lg bg-rose-50 border border-rose-200 p-3 text-sm text-rose-700">
                <b>Cannot publish yet — fix these first:</b>
                <ul class="list-disc list-inside mt-1 space-y-0.5">@foreach ($sectionErrors as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @elseif ($test->status !== 'PUBLISHED')
            <div class="mt-3 rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-700">✓ Every section has enough questions — this test is ready to publish.</div>
        @endif
        <p class="text-xs text-slate-400 mt-3">Each candidate gets <b>{{ $sumQ }}</b> questions: for every section, {{ $sections->count() > 1 ? 'its' : 'the' }} required number is picked from that section's pool (randomised within the section, never mixed). A question can be in only one section of a test.</p>
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:content-start">
    {{-- ================= Questions on the test ================= --}}
    <div class="bg-white rounded-xl shadow-sm p-5">
        <div class="flex items-center justify-between gap-3 mb-3">
            <div class="font-semibold">Questions on this test ({{ $test->testQuestions->count() }})</div>
            @if ($useSec)
                <form method="GET" action="{{ route('tests.show', $test) }}">
                    <select name="section" onchange="this.form.submit()" class="text-xs rounded-lg border border-slate-200 px-2 py-1.5 text-slate-600">
                        <option value="">All sections</option>
                        @foreach ($sections as $s)<option value="{{ $s->id }}" @selected(request('section') && (int) request('section') === $s->id)>{{ $s->title }} ({{ $s->pool_count }})</option>@endforeach
                    </select>
                </form>
            @endif
        </div>
        <div class="max-h-96 overflow-auto">
            @if ($useSec)
                @foreach ($sections as $s)
                    @continue(request('section') && (int) request('section') !== $s->id)
                    <div class="mb-3">
                        <div class="flex items-center justify-between text-[11px] font-bold uppercase tracking-wide py-1.5 sticky top-0 bg-white border-b border-slate-100">
                            <span class="text-slate-600">{{ $s->title }}</span>
                            <span class="{{ $s->pool_count >= $s->question_count ? 'text-emerald-600' : 'text-rose-600' }}">{{ $s->pool_count }} available / {{ $s->question_count }} needed</span>
                        </div>
                        <div class="divide-y">
                            @forelse ($grouped->get($s->id, collect()) as $tq)
                                <div class="py-2 flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <span class="text-xs bg-brand/10 text-brand px-1.5 rounded">{{ str_replace('_',' ',$tq->question->type) }}</span>
                                        @if ($tq->question->subject)<span class="text-[11px] text-slate-400">{{ $tq->question->subject }}@if($tq->question->topic) · {{ $tq->question->topic }}@endif</span>@endif
                                        <p class="text-sm truncate">{{ $tq->question->text }}</p>
                                    </div>
                                    @if ($test->status !== 'PUBLISHED')
                                        <form method="POST" action="{{ route('tests.removeQuestion', [$test, $tq->question]) }}">@csrf @method('DELETE')
                                            <button class="text-rose-500 text-sm" title="Remove from test">✕</button>
                                        </form>
                                    @endif
                                </div>
                            @empty
                                <p class="text-slate-400 text-xs py-3">No questions in this section yet — add some from the bank on the right.</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
                @if (! request('section') && ($grouped->get(0) ?? collect())->count())
                    <div class="mb-3">
                        <div class="text-[11px] font-bold uppercase tracking-wide py-1.5 text-amber-600 border-b border-amber-100">Unassigned — not in any section (will NOT appear in the exam)</div>
                        <div class="divide-y">
                            @foreach ($grouped->get(0) as $tq)
                                <div class="py-2 flex items-start justify-between gap-3">
                                    <p class="text-sm truncate text-slate-500">{{ $tq->question->text }}</p>
                                    @if ($test->status !== 'PUBLISHED')
                                        <form method="POST" action="{{ route('tests.removeQuestion', [$test, $tq->question]) }}">@csrf @method('DELETE')<button class="text-rose-500 text-sm">✕</button></form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @else
                <div class="divide-y">
                    @forelse ($test->testQuestions as $tq)
                        <div class="py-2 flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <span class="text-xs bg-brand/10 text-brand px-1.5 rounded">{{ str_replace('_',' ',$tq->question->type) }}</span>
                                <p class="text-sm truncate">{{ $tq->question->text }}</p>
                            </div>
                            @if ($test->status !== 'PUBLISHED')
                                <form method="POST" action="{{ route('tests.removeQuestion', [$test, $tq->question]) }}">@csrf @method('DELETE')
                                    <button class="text-rose-500 text-sm">✕</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-slate-400 text-sm py-4">No questions added yet.</p>
                    @endforelse
                </div>
            @endif
        </div>
    </div>

    {{-- ================= Add questions (filterable, section-aware) ================= --}}
    @if ($test->status !== 'PUBLISHED')
    <div class="bg-white rounded-xl shadow-sm p-5">
        <div class="font-semibold mb-3">Add questions from the bank</div>

        <form method="GET" action="{{ route('tests.show', $test) }}" class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-2 text-sm">
            @if ($useSec)<input type="hidden" name="section" value="{{ $selSection }}">@endif
            <input name="search" value="{{ $filters['search'] }}" placeholder="Search question text…" class="col-span-2 md:col-span-4 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-brand outline-none">
            <select name="subject" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-2 py-2 text-sm">
                <option value="">All subjects</option>
                @foreach ($subjects as $sub)<option value="{{ $sub }}" @selected($filters['subject']===$sub)>{{ $sub }}</option>@endforeach
            </select>
            <select name="topic" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-2 py-2 text-sm">
                <option value="">All topics</option>
                @foreach ($topics as $tp)<option value="{{ $tp }}" @selected($filters['topic']===$tp)>{{ $tp }}</option>@endforeach
            </select>
            <select name="difficulty" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-2 py-2 text-sm">
                <option value="">Any difficulty</option>
                @foreach (['EASY','MEDIUM','HARD'] as $d)<option value="{{ $d }}" @selected($filters['difficulty']===$d)>{{ ucfirst(strtolower($d)) }}</option>@endforeach
            </select>
            <select name="type" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-2 py-2 text-sm">
                <option value="">Any type</option>
                @foreach (['MCQ_SINGLE','MCQ_MULTI','TRUE_FALSE','FILL_BLANK','NUMERIC','DESCRIPTIVE'] as $t)<option value="{{ $t }}" @selected($filters['type']===$t)>{{ str_replace('_',' ',$t) }}</option>@endforeach
            </select>
            <div class="col-span-2 md:col-span-4 flex items-center gap-2">
                <button class="border border-slate-300 px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-slate-50">Apply filters</button>
                <a href="{{ route('tests.show', array_filter([$test, 'section' => $selSection])) }}" class="text-xs text-slate-500 hover:text-brand">Reset</a>
                <span class="ml-auto text-xs text-slate-400">{{ $filterCount }} matching{{ $filterCount > 100 ? ' — showing first 100' : '' }}</span>
            </div>
        </form>

        @if ($useSec && $selObj)
            <form method="POST" action="{{ route('tests.autoFillSection', [$test, $selObj]) }}" class="flex flex-wrap items-center gap-2 mb-3 rounded-lg bg-violet-50 border border-violet-100 p-3 text-sm">@csrf
                @foreach (['search','subject','topic','difficulty','type'] as $f)<input type="hidden" name="{{ $f }}" value="{{ $filters[$f] }}">@endforeach
                <span class="text-violet-800">Auto-fill <b>{{ $selObj->title }}</b> with</span>
                <input type="number" name="count" min="1" max="1000" value="{{ max(1, $selObj->question_count - $selObj->pool_count) }}" class="w-20 rounded-lg border border-violet-200 px-2 py-1 text-sm bg-white">
                <span class="text-violet-800">random questions matching the filters above</span>
                <button class="bg-violet-600 hover:bg-violet-700 text-white px-3 py-1.5 rounded-lg text-xs font-semibold">Auto-fill</button>
            </form>
        @endif

        <form method="POST" action="{{ route('tests.addQuestions', $test) }}">@csrf
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2 text-sm">
                @if ($useSec)
                    <label class="flex items-center gap-2 font-medium">Add to section
                        <select name="section_id" required class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm font-normal">
                            @foreach ($sections as $s)<option value="{{ $s->id }}" @selected($selSection===$s->id)>{{ $s->title }} ({{ $s->pool_count }}/{{ $s->question_count }})</option>@endforeach
                        </select>
                    </label>
                @endif
                <label class="flex items-center gap-1.5 text-xs text-slate-500 cursor-pointer"><input type="checkbox" onchange="document.querySelectorAll('.pickq').forEach(c => c.checked = this.checked)" class="rounded border-slate-300"> Select all shown</label>
            </div>
            <div class="max-h-80 overflow-auto divide-y mb-3">
                @forelse ($available as $q)
                    <label class="py-2 flex items-start gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="question_ids[]" value="{{ $q->id }}" class="pickq mt-1 rounded border-slate-300">
                        <span class="min-w-0">
                            <span class="text-xs bg-slate-100 px-1.5 rounded">{{ str_replace('_',' ',$q->type) }}</span>
                            @if ($q->subject)<span class="text-[11px] text-slate-400">{{ $q->subject }}@if($q->topic) · {{ $q->topic }}@endif</span>@endif
                            @if ($q->difficulty)<span class="text-[10px] uppercase text-slate-400">{{ $q->difficulty }}</span>@endif
                            {{ $q->text }}
                        </span>
                    </label>
                @empty
                    <p class="text-slate-400 py-4 text-sm">No more active questions match. <a href="{{ route('questions.create') }}" class="text-brand">Create one</a>.</p>
                @endforelse
            </div>
            <button class="bg-brand text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-brand-dark">Add selected{{ $useSec ? ' to section' : '' }}</button>
        </form>
    </div>
    @endif

    {{-- ================= Assign candidates ================= --}}
    <div class="bg-white rounded-xl shadow-sm p-5">
        <div class="font-semibold mb-3">Assigned candidates ({{ $test->assignments->count() }})</div>
        <form method="POST" action="{{ route('tests.assign', $test) }}" class="space-y-3">@csrf
            <input name="batch" placeholder="Assign whole batch (e.g. CSE-A)" class="w-full rounded-lg border-slate-300 border px-3 py-2 text-sm">
            <div class="max-h-48 overflow-auto divide-y">
                @foreach ($candidates as $c)
                    <label class="py-1.5 flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="user_ids[]" value="{{ $c->id }}" class="rounded border-slate-300">
                        {{ $c->name }} <span class="text-slate-400">{{ $c->student_id ? '('.$c->student_id.')' : $c->email }}</span>
                    </label>
                @endforeach
            </div>
            <button class="bg-brand text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-brand-dark">Assign</button>
        </form>
        @if ($test->assignments->count())
            <div class="mt-3 text-xs text-slate-500 max-h-32 overflow-auto">
                @foreach ($test->assignments as $a){{ $a->user?->name }}@if(!$loop->last), @endif @endforeach
            </div>
        @endif
    </div>
</div>
</div>
</div>
@endsection
