@extends('layouts.app')
@section('title', 'Grading')
@section('content')
<h1 class="text-2xl font-bold mb-5">Descriptive Grading Queue</h1>

<div class="space-y-4">
    @forelse ($pending as $ans)
        <div class="bg-white rounded-xl shadow-sm p-5">
            <div class="flex items-center justify-between text-sm text-slate-500 mb-2">
                <span>{{ $ans->attempt->test?->title }} · {{ $ans->attempt->candidate?->name }} <span class="text-slate-400">{{ $ans->attempt->candidate?->student_id }}</span></span>
                <span>Max {{ $ans->question->marks }} mark(s)</span>
            </div>
            <p class="font-medium mb-1">{{ $ans->question->text }}</p>
            @if ($ans->question->model_answer)
                <p class="text-xs text-slate-500 bg-slate-50 rounded p-2 mb-2"><b>Model:</b> {{ $ans->question->model_answer }}</p>
            @endif
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm mb-3 whitespace-pre-wrap">{{ $ans->text_answer ?: '(no answer)' }}</div>

            <form method="POST" action="{{ route('grading.grade', $ans) }}" class="flex flex-wrap items-end gap-3">@csrf
                <div>
                    <label class="block text-xs font-medium mb-1">Marks (0–{{ $ans->question->marks }})</label>
                    <input name="awarded_marks" type="number" step="0.5" min="0" max="{{ $ans->question->marks }}" required class="w-28 rounded-lg border-slate-300 border px-3 py-2 text-sm">
                </div>
                <div class="flex-1 min-w-48">
                    <label class="block text-xs font-medium mb-1">Feedback (optional)</label>
                    <input name="feedback" class="w-full rounded-lg border-slate-300 border px-3 py-2 text-sm">
                </div>
                <button class="bg-brand text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-brand-dark">Save grade</button>
            </form>
        </div>
    @empty
        <div class="bg-white rounded-xl shadow-sm p-10 text-center text-slate-400">Nothing to grade. 🎉</div>
    @endforelse
</div>
@endsection
