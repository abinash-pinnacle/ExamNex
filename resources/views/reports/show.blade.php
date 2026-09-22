@extends('layouts.app')
@section('title', 'Results · '.$test->title)
@section('content')
@php $fmt = fn ($n) => \App\Services\SectionService::fmt($n); $hasSec = $sections->isNotEmpty(); @endphp
<div class="lg:h-[calc(100%_-_4rem)] lg:flex lg:flex-col lg:min-h-0">
<div class="flex items-center justify-between gap-3 mb-5 shrink-0">
    <div class="min-w-0">
        <a href="{{ route('reports.index') }}" class="text-xs text-slate-400 hover:text-brand">&larr; All results</a>
        <h1 class="text-2xl font-bold truncate">{{ $test->title }} — Results</h1>
    </div>
    <a href="{{ route('reports.export', $test) }}" class="bg-brand text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-brand-dark shrink-0">⇩ Export Excel</a>
</div>
<div class="lg:flex-1 lg:min-h-0 lg:overflow-y-auto lg:pr-1">

<div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-6">
    @php $tiles = [
        ['Attempts', $summary['total']],
        ['Evaluated', $summary['evaluated']],
        ['Passed', $summary['passed']],
        ['Average', $summary['avg'] ?? '—'],
        ['Highest', $summary['high'] ?? '—'],
        ['Lowest', $summary['low'] ?? '—'],
    ]; @endphp
    @foreach ($tiles as [$l,$v])
        <div class="bg-white rounded-xl shadow-sm p-4"><div class="text-2xl font-bold text-brand">{{ $v }}</div><div class="text-xs text-slate-500 mt-1">{{ $l }}</div></div>
    @endforeach
</div>

@if ($hasSec)
    {{-- ================= Section analytics ================= --}}
    <div class="bg-white rounded-xl shadow-sm p-5 mb-6">
        <div class="flex items-center justify-between mb-3">
            <div class="font-semibold">Section analytics</div>
            <div class="text-xs text-slate-400">Evaluated attempts only · overall qualifying {{ $test->passing_marks }} / {{ $test->total_marks }}</div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
            @foreach ($sectionAnalytics as $a)
                @php $s = $a['section']; $pr = $a['passRate']; $prColor = $pr === null ? 'bg-slate-200' : ($pr >= 60 ? 'bg-emerald-500' : ($pr >= 40 ? 'bg-amber-500' : 'bg-rose-500')); @endphp
                <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-4">
                    <div class="text-[11px] font-bold text-brand uppercase tracking-wide truncate">{{ $s->title }}</div>
                    <div class="flex items-end gap-2 mt-2">
                        <div class="text-2xl font-extrabold text-slate-800 leading-none">{{ $a['avg'] !== null ? $fmt($a['avg']) : '—' }}</div>
                        <div class="text-xs text-slate-400 mb-0.5">/ {{ $a['max'] }} avg</div>
                        <div class="ml-auto text-right"><div class="text-lg font-bold leading-none {{ $pr === null ? 'text-slate-400' : ($pr >= 60 ? 'text-emerald-600' : ($pr >= 40 ? 'text-amber-500' : 'text-rose-600')) }}">{{ $pr !== null ? $pr.'%' : '—' }}</div><div class="text-[11px] text-slate-400">pass rate</div></div>
                    </div>
                    <div class="mt-2 h-1.5 rounded-full bg-slate-200 overflow-hidden"><div class="h-full rounded-full {{ $prColor }}" style="width: {{ $pr ?? 0 }}%"></div></div>
                    <div class="grid grid-cols-3 gap-1 mt-3 text-center text-xs">
                        <div><div class="font-bold text-slate-800">{{ $a['high'] !== null ? $fmt($a['high']) : '—' }}</div><div class="text-[10px] text-slate-400">Highest</div></div>
                        <div><div class="font-bold text-slate-800">{{ $a['low'] !== null ? $fmt($a['low']) : '—' }}</div><div class="text-[10px] text-slate-400">Lowest</div></div>
                        <div><div class="font-bold text-slate-800">{{ $a['qualified'] }}<span class="text-slate-400 font-normal">/{{ $a['evaluated'] }}</span></div><div class="text-[10px] text-slate-400">Qualified</div></div>
                    </div>
                    <div class="text-[11px] text-slate-400 mt-2">Qualifying {{ $fmt($s->qualifying_marks) }} · fail rate {{ $a['failRate'] !== null ? $a['failRate'].'%' : '—' }}</div>
                </div>
            @endforeach
        </div>
    </div>
@endif

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto"><table class="w-full text-sm min-w-[720px]">
        <thead class="bg-slate-50 text-slate-500 text-left">
            <tr>
                <th class="px-4 py-2">Candidate</th><th class="px-4 py-2">Student ID</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Score</th><th class="px-4 py-2">Result</th>
                @foreach ($sections as $s)<th class="px-3 py-2 text-xs whitespace-nowrap" title="{{ $s->title }} · qualifying {{ $fmt($s->qualifying_marks) }} / {{ $s->maxMarks() }}">{{ \Illuminate\Support\Str::limit($s->title, 14) }}</th>@endforeach
                <th class="px-4 py-2">Submitted</th><th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($attempts as $a)
                @php $bySec = $a->sectionResults->keyBy('section_id'); @endphp
                <tr>
                    <td class="px-4 py-2">{{ $a->candidate?->name }}</td>
                    <td class="px-4 py-2 text-slate-500">{{ $a->candidate?->student_id ?? '—' }}</td>
                    <td class="px-4 py-2"><x-status :value="$a->status" /></td>
                    <td class="px-4 py-2">{{ $a->total_score !== null ? $fmt($a->total_score).' / '.$fmt($a->max_score ?? $test->total_marks) : '—' }}</td>
                    <td class="px-4 py-2">
                        @if ($a->passed === true)<span class="text-emerald-600 font-medium">✓ PASS</span>
                        @elseif ($a->passed === false)<span class="text-rose-600 font-medium" title="{{ $a->result_reason }}">✕ FAIL</span>
                        @else — @endif
                        @if ($a->terminated)<span class="ml-1 text-xs bg-rose-100 text-rose-700 px-1.5 py-0.5 rounded">terminated</span>@endif
                        @if ($a->passed === false && $a->result_reason && ! $a->terminated)<div class="text-[11px] text-slate-400 max-w-[220px] truncate" title="{{ $a->result_reason }}">{{ $a->result_reason }}</div>@endif
                    </td>
                    @foreach ($sections as $s)
                        @php $r = $bySec->get($s->id); @endphp
                        <td class="px-3 py-2 text-xs whitespace-nowrap">
                            @if ($r)
                                <span class="font-semibold {{ $r->passed === false ? 'text-rose-600' : ($r->passed ? 'text-emerald-700' : 'text-slate-600') }}">{{ $fmt($r->score) }}/{{ $fmt($r->max_score) }}</span>
                                @if ($r->passed === true)<span class="text-emerald-600">✓</span>@elseif ($r->passed === false)<span class="text-rose-600">✕</span>@endif
                            @else <span class="text-slate-300">—</span> @endif
                        </td>
                    @endforeach
                    <td class="px-4 py-2 text-slate-500">{{ $a->submitted_at?->format('d M, H:i') ?? '—' }}</td>
                    <td class="px-4 py-2 text-right"><a href="{{ route('reports.attempt', [$test, $a]) }}" class="text-brand text-xs font-medium hover:underline whitespace-nowrap">View →</a></td>
                </tr>
            @empty
                <tr><td colspan="{{ 7 + $sections->count() }}" class="px-4 py-8 text-center text-slate-400">No attempts.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>
</div>
</div>
@endsection
