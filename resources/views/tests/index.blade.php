@extends('layouts.app')
@section('title', 'Tests')
@section('content')
<div class="lg:h-[calc(100%_-_4rem)] lg:flex lg:flex-col lg:min-h-0">
<div class="flex items-center justify-between mb-5 shrink-0">
    <h1 class="text-2xl font-bold">Tests</h1>
    <a href="{{ route('tests.create') }}" class="bg-brand text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-brand-dark">+ New Test</a>
</div>
<div class="lg:flex-1 lg:min-h-0 lg:overflow-y-auto lg:pr-1">
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto"><table class="w-full text-sm min-w-[640px]">
        <thead class="bg-slate-50 text-slate-500 text-left">
            <tr><th class="px-4 py-2">Title</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Questions</th><th class="px-4 py-2">Marks</th><th class="px-4 py-2">Duration</th><th class="px-4 py-2">Attempts</th><th class="px-4 py-2"></th></tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($tests as $t)
                <tr>
                    <td class="px-4 py-2 font-medium"><a href="{{ route('tests.show', $t) }}" class="text-brand hover:underline">{{ $t->title }}</a>
                        @if ($t->public_access)<span class="ml-1 text-xs bg-indigo-100 text-indigo-700 px-1.5 rounded">public</span>@endif
                    </td>
                    <td class="px-4 py-2"><x-status :value="$t->status" /></td>
                    <td class="px-4 py-2">{{ $t->test_questions_count }}</td>
                    <td class="px-4 py-2">{{ $t->total_marks }}</td>
                    <td class="px-4 py-2">{{ $t->duration_minutes }}m</td>
                    <td class="px-4 py-2">{{ $t->attempts_count }}</td>
                    <td class="px-4 py-2 text-right">
                        <a href="{{ route('tests.show', $t) }}" class="text-brand hover:underline">Manage</a>
                        @if ($t->status==='PUBLISHED')<a href="{{ route('tests.monitor', $t) }}" class="ml-2 text-brand hover:underline">Monitor</a>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No tests yet.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>
<div class="mt-4">{{ $tests->links() }}</div>
</div>
</div>
@endsection
