@extends('layouts.app')
@section('title', 'Results · '.$test->title)
@section('content')
<div class="flex items-center justify-between gap-3 mb-5">
    <div class="min-w-0">
        <a href="{{ route('reports.index') }}" class="text-xs text-slate-400 hover:text-brand">&larr; All results</a>
        <h1 class="text-2xl font-bold truncate">{{ $test->title }} — Results</h1>
    </div>
    <a href="{{ route('reports.export', $test) }}" class="bg-brand text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-brand-dark">⇩ Export Excel</a>
</div>

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

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto"><table class="w-full text-sm min-w-[640px]">
        <thead class="bg-slate-50 text-slate-500 text-left">
            <tr><th class="px-4 py-2">Candidate</th><th class="px-4 py-2">Student ID</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Score</th><th class="px-4 py-2">Result</th><th class="px-4 py-2">Submitted</th></tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($attempts as $a)
                <tr>
                    <td class="px-4 py-2">{{ $a->candidate?->name }}</td>
                    <td class="px-4 py-2 text-slate-500">{{ $a->candidate?->student_id ?? '—' }}</td>
                    <td class="px-4 py-2"><x-status :value="$a->status" /></td>
                    <td class="px-4 py-2">{{ $a->total_score !== null ? $a->total_score.' / '.($a->max_score ?? $test->total_marks) : '—' }}</td>
                    <td class="px-4 py-2">
                        @if ($a->passed === true)<span class="text-emerald-600 font-medium">PASS</span>
                        @elseif ($a->passed === false)<span class="text-rose-600 font-medium">FAIL</span>
                        @else — @endif
                        @if ($a->terminated)<span class="ml-1 text-xs bg-rose-100 text-rose-700 px-1.5 py-0.5 rounded">terminated</span>@endif
                    </td>
                    <td class="px-4 py-2 text-slate-500">{{ $a->submitted_at?->format('d M, H:i') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No attempts.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>
@endsection
