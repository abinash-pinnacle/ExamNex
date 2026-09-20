@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
@php
    $badge = function ($pct) {
        if ($pct > 0) return ['text-emerald-600', '↗ +'.$pct.'%'];
        if ($pct < 0) return ['text-rose-500', '↘ '.$pct.'%'];
        return ['text-slate-400', '+0%'];
    };
    $cards = [
        ['questions', 'Total Questions', $stats['questions'], $growth['questions'], 'from-blue-50 to-blue-100', 'text-blue-600', '#2563eb', 'M9 12h6M9 16h6M9 8h6M6 3h9l5 5v12a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z'],
        ['tests', 'Total Tests', $stats['tests'], $growth['tests'], 'from-emerald-50 to-emerald-100', 'text-emerald-600', '#059669', 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2'],
        ['published', 'Published Tests', $stats['published'], $growth['published'], 'from-violet-50 to-violet-100', 'text-violet-600', '#7c3aed', 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z'],
        ['candidates', 'Total Candidates', $stats['candidates'], $growth['candidates'], 'from-amber-50 to-amber-100', 'text-amber-600', '#d97706', 'M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H4v-2a4 4 0 0 1 3-3.87m6-1.13a4 4 0 1 0-4-4 4 4 0 0 0 4 4z'],
        ['attempts', 'Total Attempts', $stats['attempts'], $growth['attempts'], 'from-rose-50 to-rose-100', 'text-rose-600', '#e11d48', 'M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11'],
    ];
    $sparkPoints = function (array $vals) {
        $vals = $vals ?: [0];
        $max = max(1, max($vals));
        $n = max(1, count($vals));
        $pts = [];
        foreach ($vals as $i => $v) {
            $x = $n > 1 ? 2 + $i * (58 / ($n - 1)) : 31;
            $y = 18 - ($v / $max) * 15;
            $pts[] = round($x, 1) . ',' . round($y, 1);
        }
        return implode(' ', $pts);
    };
    // donut
    $r = 52; $c = 2 * M_PI * $r; $dash = $performance['avgPct'] / 100 * $c;
@endphp

{{-- Welcome + date --}}
<div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900">Welcome back, {{ explode(' ', auth()->user()->name)[0] }}! 👋</h1>
        <p class="text-slate-500 mt-1">Here's what's happening with your exams today.</p>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 px-5 py-3 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
            <svg class="w-5 h-5 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        </div>
        <div>
            <div class="font-semibold text-slate-800 text-sm">{{ now()->format('l, j F Y') }}</div>
            <div class="text-xs text-slate-400">Have a productive day!</div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    {{-- LEFT (2 cols) --}}
    <div class="xl:col-span-2 space-y-6">
        {{-- Stat cards --}}
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4">
            @foreach ($cards as [$skey,$label,$val,$pct,$grad,$txt,$stroke,$icon])
                @php [$bc,$bt] = $badge($pct); @endphp
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
                    <div class="w-11 h-11 rounded-xl bg-gradient-to-br {{ $grad }} flex items-center justify-center mb-3">
                        <svg class="w-5 h-5 {{ $txt }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                    </div>
                    <div class="text-2xl font-extrabold text-slate-900">{{ $val }}</div>
                    <div class="text-xs text-slate-500 mt-0.5">{{ $label }}</div>
                    <div class="flex items-center justify-between mt-2">
                        <span class="text-xs font-semibold {{ $bc }}">{{ $bt }}</span>
                        <svg width="60" height="20" viewBox="0 0 62 20" fill="none"><polyline points="{{ $sparkPoints($series[$skey] ?? []) }}" stroke="{{ $stroke }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity=".85"/></svg>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Quick actions --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            <h2 class="font-bold text-slate-800 mb-4">Quick Actions</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @php
                    $qa = [
                        [route('questions.create'), 'New Question', 'Add to question bank', 'bg-brand text-white', 'text-white/80', 'M12 5v14M5 12h14'],
                        [route('questions.ai'), 'AI Generate', 'Create with AI', 'bg-violet-50 text-violet-700', 'text-violet-400', 'M5 3v4M3 5h4M6 17v4M4 19h4M13 3l2.5 6.5L22 12l-6.5 2.5L13 21l-2.5-6.5L4 12l6.5-2.5z'],
                        [route('tests.create'), 'New Test', 'Create a new test', 'bg-blue-50 text-blue-700', 'text-blue-400', 'M12 5v14M5 12h14'],
                        [route('questions.importForm'), 'Import Excel', 'Bulk upload questions', 'bg-emerald-50 text-emerald-700', 'text-emerald-400', 'M12 15V3m0 0L8 7m4-4 4 4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2'],
                    ];
                @endphp
                @foreach ($qa as [$href,$t,$s,$cls,$ic,$icon])
                    <a href="{{ $href }}" class="rounded-xl p-4 flex items-center gap-3 transition hover:opacity-90 {{ $cls }}">
                        <span class="w-11 h-11 rounded-xl bg-white/20 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 {{ $ic }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                        </span>
                        <span><span class="block font-semibold text-sm leading-tight">{{ $t }}</span><span class="block text-xs opacity-70">{{ $s }}</span></span>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Recent attempts --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-bold text-slate-800">Recent Attempts</h2>
                <a href="{{ route('reports.index') }}" class="text-sm text-brand font-medium hover:underline">View All →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-slate-400 border-b border-slate-100">
                        <tr><th class="pb-2 font-medium">#</th><th class="pb-2 font-medium">Candidate</th><th class="pb-2 font-medium">Test</th><th class="pb-2 font-medium">Status</th><th class="pb-2 font-medium">Score</th><th class="pb-2 font-medium">Started</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse ($recentAttempts as $i => $a)
                            <tr>
                                <td class="py-3 text-slate-400">{{ $i+1 }}</td>
                                <td class="py-3">
                                    <div class="flex items-center gap-2.5">
                                        <span class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-blue-700 text-white text-xs font-bold flex items-center justify-center">{{ strtoupper(substr($a->candidate?->name ?? '?',0,2)) }}</span>
                                        <span><span class="block font-medium text-slate-700">{{ $a->candidate?->name ?? '—' }}</span><span class="block text-xs text-slate-400">{{ $a->candidate?->email }}</span></span>
                                    </div>
                                </td>
                                <td class="py-3 text-slate-600">{{ $a->test?->title ?? '—' }}</td>
                                <td class="py-3"><x-status :value="$a->status" /></td>
                                <td class="py-3 font-medium">{{ $a->total_score !== null ? rtrim(rtrim(number_format($a->total_score,1),'0'),'.').' / '.($a->max_score ?? '—') : '—' }}</td>
                                <td class="py-3 text-slate-500"><span class="block">{{ $a->started_at?->diffForHumans() }}</span><span class="block text-xs text-slate-400">{{ $a->started_at?->format('d M Y, h:i A') }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-8 text-center text-slate-400">No attempts yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- RIGHT (1 col) --}}
    <div class="space-y-6">
        {{-- Performance donut --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-bold text-slate-800">Overall Performance</h2>
                <span class="text-xs text-slate-400 border border-slate-200 rounded-lg px-2 py-1">Last 7 days</span>
            </div>
            <div class="flex justify-center py-2">
                <div class="relative w-40 h-40">
                    <svg class="w-40 h-40 -rotate-90" viewBox="0 0 120 120">
                        <circle cx="60" cy="60" r="{{ $r }}" fill="none" stroke="#eef2f7" stroke-width="12"/>
                        <circle cx="60" cy="60" r="{{ $r }}" fill="none" stroke="#2563eb" stroke-width="12" stroke-linecap="round"
                                stroke-dasharray="{{ $dash }} {{ $c }}"/>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-3xl font-extrabold text-slate-900">{{ $performance['avgPct'] }}%</span>
                        <span class="text-xs text-slate-400">Average Score</span>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-3 text-center mt-4 pt-4 border-t border-slate-100">
                <div><div class="text-lg font-bold text-slate-800">{{ $performance['attempts'] }}</div><div class="text-xs text-slate-400">Attempts</div></div>
                <div><div class="text-lg font-bold text-emerald-600">{{ $performance['passed'] }}</div><div class="text-xs text-slate-400">Passed</div></div>
                <div><div class="text-lg font-bold text-amber-500">{{ $performance['pending'] }}</div><div class="text-xs text-slate-400">Pending</div></div>
            </div>
        </div>

        {{-- Recent activity --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-bold text-slate-800">Recent Activity</h2>
            </div>
            <div class="space-y-4">
                @php
                    $actMap = [
                        'attempt.submit' => ['New attempt submitted', 'bg-blue-100 text-blue-600'],
                        'attempt.autoSubmit' => ['Attempt auto-submitted', 'bg-blue-100 text-blue-600'],
                        'attempt.start' => ['Attempt started', 'bg-sky-100 text-sky-600'],
                        'test.publish' => ['Test published', 'bg-emerald-100 text-emerald-600'],
                        'test.create' => ['Test created', 'bg-emerald-100 text-emerald-600'],
                        'user.create' => ['New candidate added', 'bg-violet-100 text-violet-600'],
                        'publicTest.register' => ['Candidate self-registered', 'bg-violet-100 text-violet-600'],
                        'question.create' => ['Question added', 'bg-amber-100 text-amber-600'],
                        'question.bulkImport' => ['Questions imported', 'bg-amber-100 text-amber-600'],
                        'answer.grade' => ['Answer graded', 'bg-rose-100 text-rose-600'],
                    ];
                @endphp
                @forelse ($activity as $log)
                    @php [$label,$cls] = $actMap[$log->action] ?? [ucwords(str_replace(['.','_'],' ', $log->action)), 'bg-slate-100 text-slate-500']; @endphp
                    <div class="flex items-start gap-3">
                        <span class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 {{ $cls }}">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 2M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0z"/></svg>
                        </span>
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-slate-700">{{ $label }}</div>
                            <div class="text-xs text-slate-400 truncate">{{ $log->actor_email ?? 'system' }} · {{ $log->created_at?->diffForHumans() }}</div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400 text-center py-4">No activity yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
