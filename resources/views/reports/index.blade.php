@extends('layouts.app')
@section('title', 'Results')
@section('content')
<div class="mb-5">
    <h1 class="text-2xl font-bold">Results</h1>
    <p class="text-sm text-slate-500 mt-0.5">Test-wise results — pass rate, average score and every candidate's outcome.</p>
</div>

@if ($tests->isEmpty())
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-10 text-center text-slate-400">
        No results yet. Publish a test and collect attempts to see results here.
    </div>
@else
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    @foreach ($tests as $t)
        @php
            $pr = $t->res_passrate;
            $prColor = $pr === null ? 'text-slate-400' : ($pr >= 60 ? 'text-emerald-600' : ($pr >= 40 ? 'text-amber-500' : 'text-rose-600'));
            $barColor = $pr === null ? 'bg-slate-200' : ($pr >= 60 ? 'bg-emerald-500' : ($pr >= 40 ? 'bg-amber-500' : 'bg-rose-500'));
        @endphp
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 flex flex-col">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="font-bold text-slate-800 truncate">{{ $t->title }}</h3>
                    <div class="text-xs text-slate-400 mt-0.5 truncate">{{ $t->subject ?: 'Assessment' }} &middot; pass {{ $t->passing_marks }}/{{ $t->total_marks }}</div>
                </div>
                <x-status :value="$t->status" />
            </div>

            <div class="flex items-end gap-3 mt-4">
                <div class="text-3xl font-extrabold leading-none {{ $prColor }}">{{ $pr !== null ? $pr.'%' : '—' }}</div>
                <div class="text-[11px] text-slate-400 leading-tight mb-1">pass<br>rate</div>
                <div class="ml-auto text-right">
                    <div class="text-lg font-bold text-slate-700 leading-none">{{ $t->res_avg ?? '—' }}</div>
                    <div class="text-[11px] text-slate-400 mt-1">avg score</div>
                </div>
            </div>
            <div class="mt-2 h-2 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full {{ $barColor }} rounded-full" style="width: {{ $pr ?? 0 }}%"></div>
            </div>

            <div class="grid grid-cols-3 gap-2 mt-4 text-center">
                <div><div class="font-bold text-slate-800">{{ $t->attempts_count }}</div><div class="text-[11px] text-slate-400">Attempts</div></div>
                <div><div class="font-bold text-emerald-600">{{ $t->res_passed }}</div><div class="text-[11px] text-slate-400">Passed</div></div>
                <div><div class="font-bold text-rose-600">{{ $t->res_failed }}</div><div class="text-[11px] text-slate-400">Failed</div></div>
            </div>

            <div class="flex items-center gap-2 mt-4 pt-4 border-t border-slate-100">
                <a href="{{ route('reports.show', $t) }}" class="flex-1 text-center bg-brand text-white text-sm font-medium py-2 rounded-lg hover:bg-brand-dark">View results</a>
                <a href="{{ route('reports.export', $t) }}" class="text-center border border-slate-300 text-slate-600 text-sm py-2 px-3 rounded-lg hover:bg-slate-50">⇩ Excel</a>
            </div>
        </div>
    @endforeach
</div>
@endif
@endsection
