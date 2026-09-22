@extends('layouts.app')
@section('title', 'Grading')
@section('content')
<div class="lg:h-[calc(100%_-_4rem)] lg:flex lg:flex-col lg:min-h-0">
<h1 class="text-2xl font-bold mb-5 shrink-0">Descriptive Grading Queue</h1>
<div class="lg:flex-1 lg:min-h-0 lg:overflow-y-auto lg:pr-1">

@if ($pending->count())
<div class="bg-blue-50 border border-blue-100 rounded-xl p-4 mb-4 text-sm text-slate-600 leading-relaxed">
    <b class="text-slate-800">Score written (descriptive) answers here.</b> Compare each answer to the <b>Model</b> answer above → enter <b>Marks (0–max)</b> → click <b>Save grade</b>.
    MCQ and True/False questions are graded automatically, so only written answers appear here. Once all of a candidate's answers are graded, their <b>result (Pass/Fail)</b> is calculated automatically.
</div>
@endif

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
            <div class="text-xs font-semibold text-slate-500 mb-1">Candidate's answer</div>
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm mb-3 whitespace-pre-wrap">{{ $ans->text_answer ?: '(no answer)' }}</div>

            <form method="POST" action="{{ route('grading.grade', $ans) }}" class="flex flex-wrap items-end gap-3">@csrf
                <div>
                    <label class="block text-xs font-medium mb-1">Marks (0–{{ $ans->question->marks }}) <span class="text-rose-500">*</span></label>
                    <input name="awarded_marks" type="number" step="0.5" min="0" max="{{ $ans->question->marks }}" required class="w-28 rounded-lg border-slate-300 border px-3 py-2 text-sm">
                    <div class="flex gap-1 mt-1">
                        <button type="button" onclick="qm(this,0)" class="text-xs px-2 py-0.5 rounded border border-slate-200 text-slate-600 hover:bg-slate-50">0</button>
                        @if ($ans->question->marks > 1)
                            <button type="button" onclick="qm(this,{{ $ans->question->marks / 2 }})" class="text-xs px-2 py-0.5 rounded border border-slate-200 text-slate-600 hover:bg-slate-50">Half</button>
                        @endif
                        <button type="button" onclick="qm(this,{{ $ans->question->marks }})" class="text-xs px-2 py-0.5 rounded border border-emerald-200 text-emerald-700 hover:bg-emerald-50">Full</button>
                    </div>
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
</div>
</div>
@push('scripts')
<script>
    function qm(btn, v){ const i = btn.closest('form').querySelector('[name=awarded_marks]'); i.value = v; i.focus(); }
</script>
@endpush
@endsection
