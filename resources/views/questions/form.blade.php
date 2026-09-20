@extends('layouts.app')
@section('title', $question ? 'Edit Question' : 'New Question')
@section('content')
@php
    $editing = (bool) $question;
    $type = old('type', $question->type ?? 'MCQ_SINGLE');
    $opts = old('option_text');
    if ($opts === null && $editing) {
        $opts = $question->options->pluck('text')->all();
        $correctIdx = $question->options->values()->filter(fn($o)=>$o->is_correct)->keys()->all();
    } else {
        $opts = $opts ?: ['', '', '', ''];
        $correctIdx = array_map('intval', (array) old('option_correct', []));
    }
    $folderNames = $folders->pluck('name')->unique()->values();
    $subjectNames = $folders->flatMap->subjects->pluck('name')->unique()->values();
    $topicNames = $folders->flatMap->subjects->flatMap->topics->pluck('name')->unique()->values();
@endphp

<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold mb-5">{{ $editing ? 'Edit' : 'New' }} Question</h1>

    <form method="POST" action="{{ $editing ? route('questions.update', $question) : route('questions.store') }}"
          class="bg-white rounded-xl shadow-sm p-6 space-y-5" id="qform">
        @csrf
        @if ($editing) @method('PUT') @endif

        {{-- Hierarchy --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Folder</label>
                <input name="folder" list="folders" value="{{ old('folder', $question->category ?? '') }}" required
                       class="w-full rounded-lg border-slate-300 border px-3 py-2">
                <datalist id="folders">@foreach ($folderNames as $n)<option value="{{ $n }}">@endforeach</datalist>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Subject</label>
                <input name="subject" list="subjects" value="{{ old('subject', $question->subject ?? '') }}" required
                       class="w-full rounded-lg border-slate-300 border px-3 py-2">
                <datalist id="subjects">@foreach ($subjectNames as $n)<option value="{{ $n }}">@endforeach</datalist>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Topic</label>
                <input name="topic" list="topics" value="{{ old('topic', $question->topic ?? '') }}" required
                       class="w-full rounded-lg border-slate-300 border px-3 py-2">
                <datalist id="topics">@foreach ($topicNames as $n)<option value="{{ $n }}">@endforeach</datalist>
            </div>
        </div>

        {{-- Type + meta --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="col-span-2">
                <label class="block text-sm font-medium mb-1">Type</label>
                <select name="type" id="type" class="w-full rounded-lg border-slate-300 border px-3 py-2">
                    @foreach (['MCQ_SINGLE'=>'MCQ (single)','MCQ_MULTI'=>'MCQ (multiple)','TRUE_FALSE'=>'True / False','FILL_BLANK'=>'Fill in the blank','NUMERIC'=>'Numeric','DESCRIPTIVE'=>'Descriptive'] as $val=>$lbl)
                        <option value="{{ $val }}" @selected($type===$val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Marks</label>
                <input name="marks" type="number" min="1" value="{{ old('marks', $question->marks ?? 1) }}" class="w-full rounded-lg border-slate-300 border px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Negative</label>
                <input name="negative_marks" type="number" step="0.25" min="0" value="{{ old('negative_marks', $question->negative_marks ?? 0) }}" class="w-full rounded-lg border-slate-300 border px-3 py-2">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Difficulty</label>
                <select name="difficulty" class="w-full rounded-lg border-slate-300 border px-3 py-2">
                    <option value="">—</option>
                    @foreach (['EASY','MEDIUM','HARD'] as $d)<option value="{{ $d }}" @selected(old('difficulty', $question->difficulty ?? '')===$d)>{{ $d }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Status</label>
                <select name="status" class="w-full rounded-lg border-slate-300 border px-3 py-2">
                    @foreach (['ACTIVE','DRAFT','ARCHIVED'] as $s)<option value="{{ $s }}" @selected(old('status', $question->status ?? 'ACTIVE')===$s)>{{ $s }}</option>@endforeach
                </select>
            </div>
        </div>

        {{-- Question text --}}
        <div>
            <label class="block text-sm font-medium mb-1">Question text</label>
            <textarea name="text" id="qtext" rows="3" required class="w-full rounded-lg border-slate-300 border px-3 py-2">{{ old('text', $question->text ?? '') }}</textarea>
            <p id="dupwarn" class="text-xs text-rose-600 mt-1 hidden"></p>
        </div>

        {{-- MCQ options --}}
        <div data-type="MCQ_SINGLE MCQ_MULTI" class="type-block">
            <label class="block text-sm font-medium mb-2">Options <span class="text-slate-400 font-normal">(tick the correct one/s)</span></label>
            <div id="options" class="space-y-2">
                @foreach ($opts as $i => $ot)
                    <div class="flex items-center gap-2 opt-row">
                        <input type="{{ $type==='MCQ_MULTI'?'checkbox':'radio' }}" name="option_correct[]" value="{{ $i }}" @checked(in_array($i,$correctIdx)) class="correct-toggle">
                        <input name="option_text[]" value="{{ $ot }}" placeholder="Option {{ $i+1 }}" class="flex-1 rounded-lg border-slate-300 border px-3 py-2">
                        <button type="button" class="text-rose-500 remove-opt">✕</button>
                    </div>
                @endforeach
            </div>
            <button type="button" id="addopt" class="mt-2 text-sm text-brand hover:underline">+ Add option</button>
        </div>

        {{-- True/False --}}
        <div data-type="TRUE_FALSE" class="type-block">
            <label class="block text-sm font-medium mb-1">Correct answer</label>
            <select name="bool_answer" class="w-full rounded-lg border-slate-300 border px-3 py-2">
                <option value="">—</option>
                <option value="1" @selected(old('bool_answer', isset($question)&&$question->bool_answer===true?'1':'')==='1')>True</option>
                <option value="0" @selected(old('bool_answer', isset($question)&&$question->bool_answer===false?'0':'')==='0')>False</option>
            </select>
        </div>

        {{-- Fill blank --}}
        <div data-type="FILL_BLANK" class="type-block">
            <label class="block text-sm font-medium mb-1">Accepted answer(s) <span class="text-slate-400 font-normal">— separate alternatives with |</span></label>
            <input name="correct_text" value="{{ old('correct_text', $question->correct_text ?? '') }}" class="w-full rounded-lg border-slate-300 border px-3 py-2">
        </div>

        {{-- Numeric --}}
        <div data-type="NUMERIC" class="type-block grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Numeric answer</label>
                <input name="numeric_answer" type="number" step="any" value="{{ old('numeric_answer', $question->numeric_answer ?? '') }}" class="w-full rounded-lg border-slate-300 border px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Tolerance (±)</label>
                <input name="numeric_tolerance" type="number" step="any" min="0" value="{{ old('numeric_tolerance', $question->numeric_tolerance ?? 0) }}" class="w-full rounded-lg border-slate-300 border px-3 py-2">
            </div>
        </div>

        {{-- Descriptive --}}
        <div data-type="DESCRIPTIVE" class="type-block">
            <label class="block text-sm font-medium mb-1">Model answer <span class="text-slate-400 font-normal">(for grader reference)</span></label>
            <textarea name="model_answer" rows="3" class="w-full rounded-lg border-slate-300 border px-3 py-2">{{ old('model_answer', $question->model_answer ?? '') }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Explanation <span class="text-slate-400 font-normal">(optional)</span></label>
            <textarea name="explanation" rows="2" class="w-full rounded-lg border-slate-300 border px-3 py-2">{{ old('explanation', $question->explanation ?? '') }}</textarea>
        </div>

        <div class="flex gap-3 pt-2">
            <button class="bg-brand text-white px-5 py-2.5 rounded-lg font-medium hover:bg-brand-dark">{{ $editing ? 'Save changes' : 'Create question' }}</button>
            <a href="{{ route('questions.index') }}" class="px-5 py-2.5 rounded-lg border border-slate-300 hover:bg-slate-50">Cancel</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
(function () {
    const typeSel = document.getElementById('type');
    function refreshType() {
        const t = typeSel.value;
        document.querySelectorAll('.type-block').forEach(el => {
            el.style.display = el.dataset.type.split(' ').includes(t) ? '' : 'none';
        });
        // radio vs checkbox for correct toggles
        const isMulti = t === 'MCQ_MULTI';
        document.querySelectorAll('.correct-toggle').forEach(cb => { cb.type = isMulti ? 'checkbox' : 'radio'; });
    }
    typeSel.addEventListener('change', refreshType);
    refreshType();

    // MCQ option add/remove
    const box = document.getElementById('options');
    document.getElementById('addopt').addEventListener('click', () => {
        const i = box.querySelectorAll('.opt-row').length;
        const isMulti = typeSel.value === 'MCQ_MULTI';
        const row = document.createElement('div');
        row.className = 'flex items-center gap-2 opt-row';
        row.innerHTML = `<input type="${isMulti?'checkbox':'radio'}" name="option_correct[]" value="${i}" class="correct-toggle">
            <input name="option_text[]" placeholder="Option ${i+1}" class="flex-1 rounded-lg border-slate-300 border px-3 py-2">
            <button type="button" class="text-rose-500 remove-opt">✕</button>`;
        box.appendChild(row);
    });
    box.addEventListener('click', e => {
        if (e.target.classList.contains('remove-opt')) {
            e.target.closest('.opt-row').remove();
            // reindex values
            box.querySelectorAll('.opt-row').forEach((r, idx) => { r.querySelector('.correct-toggle').value = idx; });
        }
    });

    // Duplicate pre-check
    const qtext = document.getElementById('qtext');
    const warn = document.getElementById('dupwarn');
    qtext.addEventListener('blur', async () => {
        const text = qtext.value.trim();
        if (!text) return;
        try {
            const res = await fetch('{{ route('questions.checkDuplicate') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify({ text })
            });
            const data = await res.json();
            if (data.duplicate) {
                warn.textContent = `⚠ Possible duplicate — already in ${data.duplicate.folder} → ${data.duplicate.subject} → ${data.duplicate.topic}`;
                warn.classList.remove('hidden');
            } else { warn.classList.add('hidden'); }
        } catch (_) {}
    });
})();
</script>
@endpush
@endsection
