@extends('layouts.app')
@section('title', 'Result · '.($attempt->candidate?->name ?? 'Candidate'))
@section('content')
@php
    $fmt = fn ($n) => \App\Services\SectionService::fmt($n);
    $results = $attempt->sectionResults;
    $maxScore = $attempt->max_score ?? $test->total_marks;
    $pending = $attempt->status !== 'EVALUATED';
@endphp
<div class="lg:h-[calc(100%_-_4rem)] lg:flex lg:flex-col lg:min-h-0">
<div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 mb-5 shrink-0">
    <div class="min-w-0">
        <a href="{{ route('reports.show', $test) }}" class="text-xs text-slate-400 hover:text-brand">&larr; {{ $test->title }} results</a>
        <h1 class="text-2xl font-bold truncate">{{ $attempt->candidate?->name ?? 'Candidate' }}</h1>
        <div class="text-sm text-slate-500">{{ $attempt->candidate?->student_id ? 'ID '.$attempt->candidate->student_id.' · ' : '' }}{{ $attempt->candidate?->email }} · <x-status :value="$attempt->status" /></div>
    </div>
    <div class="text-right text-xs text-slate-400 shrink-0">
        Submitted {{ $attempt->submitted_at?->format('d M Y, H:i') ?? '—' }}<br>
        Violations: {{ $attempt->violations }}{{ $attempt->terminated ? ' · TERMINATED' : '' }}
    </div>
</div>
<div class="lg:flex-1 lg:min-h-0 lg:overflow-y-auto lg:pr-1">

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-5 text-center">
        <div class="text-xs text-slate-400">Overall Score</div>
        <div class="text-4xl font-extrabold text-slate-900 mt-1">{{ $attempt->total_score !== null ? $fmt($attempt->total_score) : '—' }} <span class="text-lg text-slate-400 font-semibold">/ {{ $fmt($maxScore) }}</span></div>
        @if ($test->passing_marks > 0)<div class="text-xs text-slate-400 mt-1">Overall qualifying: {{ $test->passing_marks }}</div>@endif
    </div>
    <div class="rounded-xl shadow-sm p-5 text-center border {{ $pending ? 'bg-white border-slate-100' : ($attempt->passed ? 'bg-emerald-50 border-emerald-200' : 'bg-rose-50 border-rose-200') }}">
        <div class="text-xs {{ $pending ? 'text-slate-400' : ($attempt->passed ? 'text-emerald-600' : 'text-rose-600') }}">Overall Status</div>
        <div class="text-4xl font-extrabold mt-1 {{ $pending ? 'text-slate-400' : ($attempt->passed ? 'text-emerald-700' : 'text-rose-700') }}">{{ $pending ? 'PENDING' : ($attempt->passed ? '✓ PASS' : '✕ FAIL') }}</div>
        @if ($pending)<div class="text-xs text-slate-400 mt-1">Descriptive answers awaiting grading</div>@endif
    </div>
    <div class="bg-white rounded-xl shadow-sm p-5">
        <div class="text-xs text-slate-400 mb-2">Answers</div>
        <div class="grid grid-cols-3 gap-2 text-center">
            <div><div class="text-2xl font-extrabold text-emerald-600">{{ $counts['correct'] }}</div><div class="text-[11px] text-slate-400">Correct</div></div>
            <div><div class="text-2xl font-extrabold text-rose-600">{{ $counts['wrong'] }}</div><div class="text-[11px] text-slate-400">Wrong</div></div>
            <div><div class="text-2xl font-extrabold text-slate-500">{{ $counts['unanswered'] }}</div><div class="text-[11px] text-slate-400">Unattempted</div></div>
        </div>
    </div>
</div>

@if (! $pending && ! $attempt->passed && $attempt->result_reason)
    <div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-800 mb-6"><b>Reason:</b> {{ $attempt->result_reason }}</div>
@endif

@if ($results->count())
    <div class="bg-white rounded-xl shadow-sm p-5 mb-6">
        <div class="font-semibold mb-3">Section Performance</div>
        <div class="overflow-x-auto"><table class="w-full text-sm min-w-[640px]">
            <thead class="bg-slate-50 text-slate-500 text-left text-xs uppercase tracking-wide">
                <tr><th class="px-3 py-2">Section</th><th class="px-3 py-2 text-right">Score</th><th class="px-3 py-2 text-right">Max</th><th class="px-3 py-2 text-right">Required</th><th class="px-3 py-2">Percent</th><th class="px-3 py-2 text-center">Correct / Wrong / Skipped</th><th class="px-3 py-2 text-center">Status</th></tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($results as $r)
                    @php $pct = $r->percent(); @endphp
                    <tr class="{{ $r->passed === false ? 'bg-rose-50/40' : '' }}">
                        <td class="px-3 py-2 font-medium text-slate-800">{{ $r->section_title }}@if(!$r->is_mandatory) <span class="text-[10px] text-slate-400">(optional)</span>@endif</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ $fmt($r->score) }}</td>
                        <td class="px-3 py-2 text-right text-slate-500">{{ $fmt($r->max_score) }}</td>
                        <td class="px-3 py-2 text-right text-slate-500">{{ $fmt($r->qualifying_marks) }}</td>
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-2"><div class="w-24 h-1.5 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full {{ $r->passed === false ? 'bg-rose-500' : 'bg-emerald-500' }}" style="width: {{ $pct ?? 0 }}%"></div></div><span class="text-xs text-slate-500">{{ $pct !== null ? $pct.'%' : '—' }}</span></div>
                        </td>
                        <td class="px-3 py-2 text-center text-xs"><span class="text-emerald-600 font-semibold">{{ $r->correct }}</span> / <span class="text-rose-600 font-semibold">{{ $r->wrong }}</span> / <span class="text-slate-500">{{ $r->unanswered }}</span></td>
                        <td class="px-3 py-2 text-center">
                            @if ($r->passed === true)<span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700">✓ PASS</span>
                            @elseif ($r->passed === false)<span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-700">✕ FAIL</span>
                            @else<span class="text-xs text-slate-400">Pending</span>@endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    </div>
@endif

@if (! $pending)
    <div class="rounded-xl py-3 text-center font-extrabold text-lg text-white {{ $attempt->passed ? 'bg-emerald-600' : 'bg-rose-600' }}">FINAL RESULT: {{ $attempt->passed ? 'PASS ✓' : 'FAIL ✕' }}</div>
@endif
</div>
</div>
@endsection
