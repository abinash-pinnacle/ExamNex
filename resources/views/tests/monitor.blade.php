@extends('layouts.app')
@section('title', 'Monitor · '.$test->title)
@section('content')
<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-2xl font-bold">Live Monitor</h1>
        <p class="text-slate-500 text-sm">{{ $test->title }} · {{ $totalQ }} questions</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('reports.show', $test) }}" class="border border-slate-200 px-3 py-2 rounded-lg text-sm hover:bg-slate-50">View report</a>
        <button onclick="location.reload()" class="bg-brand text-white px-3 py-2 rounded-lg text-sm font-medium hover:bg-brand-dark">↻ Refresh</button>
    </div>
</div>

{{-- Summary --}}
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
    @php
        $tiles = [
            ['Assigned', $summary['assigned'], 'text-slate-800', 'bg-white'],
            ['Not started', $summary['not_started'], 'text-slate-500', 'bg-white'],
            ['In progress', $summary['in_progress'], 'text-amber-600', 'bg-white'],
            ['Completed', $summary['completed'], 'text-emerald-600', 'bg-white'],
            ['Flagged / cheating', $summary['flagged'], 'text-rose-600', 'bg-rose-50'],
        ];
    @endphp
    @foreach ($tiles as [$label,$val,$txt,$bg])
        <div class="{{ $bg }} rounded-2xl shadow-sm border border-slate-100 p-4">
            <div class="text-3xl font-extrabold {{ $txt }}">{{ $val }}</div>
            <div class="text-xs text-slate-500 mt-1">{{ $label }}</div>
        </div>
    @endforeach
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="overflow-x-auto"><table class="w-full text-sm min-w-[640px]">
        <thead class="bg-slate-50 text-slate-500 text-left">
            <tr>
                <th class="px-4 py-3">Candidate</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Progress</th>
                <th class="px-4 py-3">Violations</th>
                <th class="px-4 py-3">Score</th>
                <th class="px-4 py-3">Time</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse ($rows as $r)
                @php $a = $r->attempt; $flagged = $a && ($a->terminated || $a->violations > 0); @endphp
                <tr class="{{ $flagged ? 'bg-rose-50/50' : '' }}">
                    <td class="px-4 py-3">
                        <div class="font-medium text-slate-800">{{ $r->user?->name }}</div>
                        <div class="text-xs text-slate-400">{{ $r->user?->student_id ?? $r->user?->email }} {{ $r->user?->batch ? '· '.$r->user->batch : '' }}</div>
                    </td>
                    <td class="px-4 py-3">
                        @php
                            $map = [
                                'NOT_STARTED' => ['Not started','bg-slate-100 text-slate-500'],
                                'IN_PROGRESS' => ['In progress','bg-amber-100 text-amber-700'],
                                'SUBMITTED' => ['Submitted','bg-sky-100 text-sky-700'],
                                'AUTO_SUBMITTED' => ['Auto-submitted','bg-sky-100 text-sky-700'],
                                'EVALUATED' => ['Completed','bg-emerald-100 text-emerald-700'],
                            ];
                            [$lbl,$cls] = $map[$r->status] ?? [$r->status,'bg-slate-100 text-slate-500'];
                        @endphp
                        <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold {{ $cls }}">{{ $lbl }}</span>
                        @if ($a && $a->terminated)
                            <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold bg-rose-600 text-white ml-1">TERMINATED</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if ($r->status === 'NOT_STARTED')
                            <span class="text-slate-400">—</span>
                        @else
                            <div class="flex items-center gap-2">
                                <div class="w-24 h-2 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-brand rounded-full" style="width: {{ $r->total ? min(100, round($r->answered / $r->total * 100)) : 0 }}%"></div>
                                </div>
                                <span class="text-xs text-slate-500">{{ $r->answered }}/{{ $r->total }}</span>
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if ($a && $a->violations > 0)
                            <span class="font-bold text-rose-600">⚠ {{ $a->violations }}</span>
                        @else
                            <span class="text-slate-300">0</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if ($a && $a->status === 'EVALUATED')
                            <span class="font-semibold {{ $a->passed ? 'text-emerald-600' : 'text-rose-600' }}">{{ rtrim(rtrim(number_format($a->total_score ?? 0, 1), '0'), '.') }}/{{ $a->max_score ?? $test->total_marks }}</span>
                            <span class="text-xs {{ $a->passed ? 'text-emerald-600' : 'text-rose-600' }}">{{ $a->passed ? 'PASS' : 'FAIL' }}</span>
                        @else
                            <span class="text-slate-300">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-500 text-xs">
                        @if ($a)
                            <div>Start: {{ $a->started_at?->format('d M, H:i') }}</div>
                            @if ($a->submitted_at)<div>End: {{ $a->submitted_at?->format('d M, H:i') }}</div>@endif
                        @else — @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">No candidates assigned to this test yet.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>
<p class="text-xs text-slate-400 mt-3">Auto-refreshes every 15 seconds · rows highlighted red = violations / terminated (possible cheating).</p>

@push('scripts')<script>setTimeout(() => location.reload(), 15000);</script>@endpush
@endsection
