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

    // Multi-blank (MCQ_BLANKS) prefill: one group per blank, each with its options.
    $blanks = [];
    if (old('blank_option') !== null) {
        $bc = (array) old('blank_correct', []);
        foreach ((array) old('blank_option') as $i => $texts) {
            $ci = isset($bc[$i]) && $bc[$i] !== '' ? (int) $bc[$i] : -1;
            $o = [];
            foreach ((array) $texts as $j => $t) { $o[] = ['text' => $t, 'correct' => ((int) $j === $ci)]; }
            $blanks[] = ['options' => $o];
        }
    } elseif ($editing && $type === 'MCQ_BLANKS') {
        foreach ($question->options->groupBy('option_group') as $opts) {
            $blanks[] = ['options' => $opts->map(fn ($o) => ['text' => $o->text, 'correct' => (bool) $o->is_correct])->values()->all()];
        }
    }
@endphp

<div class="max-w-3xl mx-auto lg:h-[calc(100%_-_4rem)] lg:flex lg:flex-col lg:min-h-0">
    <h1 class="text-2xl font-bold mb-5 shrink-0">{{ $editing ? 'Edit' : 'New' }} Question</h1>

    <div class="lg:flex-1 lg:min-h-0 lg:overflow-y-auto lg:pr-1">
    <form method="POST" action="{{ $editing ? route('questions.update', $question) : route('questions.store') }}"
          enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm p-6 space-y-5" id="qform">
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
                    @foreach (['MCQ_SINGLE'=>'MCQ (single)','MCQ_MULTI'=>'MCQ (multiple)','MCQ_BLANKS'=>'Multi-blank (fill each blank)','TRUE_FALSE'=>'True / False','FILL_BLANK'=>'Fill in the blank','NUMERIC'=>'Numeric','DESCRIPTIVE'=>'Descriptive'] as $val=>$lbl)
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

        {{-- Question image (e.g. a reasoning / puzzle diagram) --}}
        <div>
            <label class="block text-sm font-medium mb-1">Image <span class="text-slate-400 font-normal">(optional — for reasoning / puzzle diagrams)</span></label>
            @if ($editing && $question->image_path)
                <div class="mb-2">
                    <img src="{{ $question->image_path }}" class="max-h-48 rounded-lg border border-slate-200">
                    <label class="flex items-center gap-2 text-sm text-rose-600 mt-1 cursor-pointer"><input type="checkbox" name="remove_image" value="1" class="rounded border-slate-300"> Remove this image</label>
                </div>
            @endif
            <input type="file" name="image" accept="image/png,image/jpeg,image/webp,image/gif" onchange="previewImg(this)"
                   class="block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-brand file:text-white file:text-sm file:font-medium hover:file:bg-brand-dark">
            <img id="imgPreview" class="hidden max-h-48 rounded-lg border border-slate-200 mt-2">
            <p class="text-xs text-slate-400 mt-1">PNG, JPG, WEBP or GIF · up to 4 MB. It appears above the options during the exam.</p>
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

        {{-- Multi-blank (each blank has its own options; tick the correct one per blank) --}}
        <div data-type="MCQ_BLANKS" class="type-block">
            <label class="block text-sm font-medium mb-1">Blanks
                <span class="text-slate-400 font-normal">— put <code class="bg-slate-100 px-1 rounded">___</code> in the question for each blank; add a blank below for each, and tick its correct option</span>
            </label>
            <div id="blanks" class="space-y-3"></div>
            <button type="button" id="addblank" class="mt-2 text-sm font-medium text-brand hover:underline">+ Add blank</button>

            <template id="blankTpl">
                <div class="blank rounded-xl border border-slate-200 bg-slate-50/50 p-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="blank-title text-sm font-bold text-brand">Blank 1</span>
                        <button type="button" onclick="removeBlank(this)" class="text-rose-500 text-xs hover:underline">Remove blank</button>
                    </div>
                    <div class="blank-opts space-y-1.5"></div>
                    <button type="button" onclick="addBlankOption(this)" class="mt-1.5 text-xs text-brand hover:underline">+ Add option</button>
                </div>
            </template>
            <template id="blankOptTpl">
                <div class="flex items-center gap-2 bopt-row">
                    <input type="radio" class="bopt-correct shrink-0" title="Mark as the correct option for this blank">
                    <input type="text" class="bopt-text flex-1 rounded-lg border-slate-300 border px-3 py-1.5 text-sm bg-white" placeholder="Option text">
                    <button type="button" onclick="this.closest('.bopt-row').remove();bReindex()" class="text-rose-500 text-sm shrink-0">✕</button>
                </div>
            </template>
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
</div>

@push('scripts')
<script>
function previewImg(input) {
    const img = document.getElementById('imgPreview');
    if (input.files && input.files[0]) { img.src = URL.createObjectURL(input.files[0]); img.classList.remove('hidden'); }
}
// ---- Multi-blank builder ----
function bReindex() {
    document.querySelectorAll('#blanks .blank').forEach((blank, i) => {
        blank.querySelector('.blank-title').textContent = 'Blank ' + (i + 1);
        blank.querySelectorAll('.bopt-row').forEach((row, j) => {
            row.querySelector('.bopt-correct').name = 'blank_correct[' + i + ']';
            row.querySelector('.bopt-correct').value = j;
            row.querySelector('.bopt-text').name = 'blank_option[' + i + '][' + j + ']';
        });
    });
}
function addBlankOptRow(blank, o) {
    const row = document.getElementById('blankOptTpl').content.cloneNode(true).querySelector('.bopt-row');
    if (o) { row.querySelector('.bopt-text').value = o.text || ''; if (o.correct) row.querySelector('.bopt-correct').checked = true; }
    blank.querySelector('.blank-opts').appendChild(row);
}
function addBlankOption(btn) { addBlankOptRow(btn.closest('.blank')); bReindex(); }
function removeBlank(btn) { btn.closest('.blank').remove(); bReindex(); }
function addBlank(data) {
    const blank = document.getElementById('blankTpl').content.cloneNode(true).querySelector('.blank');
    document.getElementById('blanks').appendChild(blank);
    const opts = (data && data.options && data.options.length) ? data.options
        : [{ text: '', correct: true }, { text: '', correct: false }, { text: '', correct: false }];
    opts.forEach(o => addBlankOptRow(blank, o));
    bReindex();
}
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
        // When switching to multi-blank with nothing set up yet, seed two blanks.
        if (t === 'MCQ_BLANKS' && document.querySelectorAll('#blanks .blank').length === 0) { addBlank(); addBlank(); }
    }
    // Prefill existing / old() blanks before first refresh.
    (@json($blanks)).forEach(b => addBlank(b));
    document.getElementById('addblank').addEventListener('click', () => addBlank());
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
