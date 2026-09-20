@extends('layouts.app')
@section('title', $test->title)
@section('content')
<div class="flex items-start justify-between mb-5">
    <div>
        <h1 class="text-2xl font-bold">{{ $test->title }}</h1>
        <div class="flex items-center gap-2 mt-1 text-sm text-slate-500">
            <x-status :value="$test->status" />
            <span>{{ $test->total_marks }} marks · {{ $test->duration_minutes }} min · pass {{ $test->passing_marks }}</span>
        </div>
    </div>
    <div class="flex gap-2">
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

@if ($publicUrl)
    <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 mb-6">
        <div class="text-sm font-medium text-indigo-800 mb-1">Shared public link (students self-register)</div>
        <div class="flex gap-2">
            <input readonly value="{{ $publicUrl }}" class="flex-1 rounded-lg border-indigo-200 border px-3 py-2 text-sm bg-white" id="publicUrl">
            <button onclick="navigator.clipboard.writeText(document.getElementById('publicUrl').value)" class="bg-indigo-600 text-white px-4 rounded-lg text-sm">Copy</button>
        </div>
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Questions on the test --}}
    <div class="bg-white rounded-xl shadow-sm p-5">
        <div class="font-semibold mb-3">Questions ({{ $test->testQuestions->count() }})</div>
        <div class="divide-y max-h-96 overflow-auto">
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
    </div>

    {{-- Add questions --}}
    @if ($test->status !== 'PUBLISHED')
    <div class="bg-white rounded-xl shadow-sm p-5">
        <div class="font-semibold mb-3">Add questions from the bank</div>
        <form method="POST" action="{{ route('tests.addQuestions', $test) }}">@csrf
            <div class="max-h-80 overflow-auto divide-y mb-3">
                @forelse ($available as $q)
                    <label class="py-2 flex items-start gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="question_ids[]" value="{{ $q->id }}" class="mt-1 rounded border-slate-300">
                        <span><span class="text-xs bg-slate-100 px-1.5 rounded">{{ str_replace('_',' ',$q->type) }}</span> {{ $q->text }}</span>
                    </label>
                @empty
                    <p class="text-slate-400 py-4">No more active questions to add. <a href="{{ route('questions.create') }}" class="text-brand">Create one</a>.</p>
                @endforelse
            </div>
            <button class="bg-brand text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-brand-dark">Add selected</button>
        </form>
    </div>
    @endif

    {{-- Assign candidates --}}
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
@endsection
