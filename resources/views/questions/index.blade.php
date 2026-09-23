@extends('layouts.app')
@section('title', 'Question Bank')
@section('content')
<div class="lg:h-[calc(100%_-_4rem)] lg:flex lg:flex-col lg:min-h-0">
<div class="flex items-center justify-between mb-5 shrink-0">
    <h1 class="text-2xl font-bold">Question Bank</h1>
    <div class="flex gap-2">
        <a href="{{ route('questions.ai') }}" class="bg-white border border-slate-300 px-3 py-2 rounded-lg text-sm hover:bg-slate-50">✨ AI Generate</a>
        <a href="{{ route('questions.importForm') }}" class="bg-white border border-slate-300 px-3 py-2 rounded-lg text-sm hover:bg-slate-50">⇪ Import Excel</a>
        <a href="{{ route('questions.create') }}" class="bg-brand text-white px-3 py-2 rounded-lg text-sm font-medium hover:bg-brand-dark">+ New Question</a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6 lg:flex-1 lg:min-h-0">
    {{-- Folder tree + management --}}
    <aside class="lg:col-span-1 min-w-0 space-y-4 lg:overflow-y-auto lg:overflow-x-hidden lg:min-h-0 lg:pr-1">
        {{-- Tree --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
            <div class="flex items-center justify-between mb-3">
                <span class="font-bold text-slate-800 text-sm">Question Bank</span>
                <span class="text-xs text-slate-400">{{ $totalQuestions }} total</span>
            </div>

            <a href="{{ route('questions.index') }}"
               class="flex items-center justify-between px-3 py-2 rounded-lg mb-1 text-sm font-medium {{ !request('topic_id') && !request('subject') && !request('category') ? 'bg-brand text-white' : 'text-slate-600 hover:bg-slate-50' }}">
                <span class="flex items-center gap-2"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7h18M3 12h18M3 17h18"/></svg> All questions</span>
                <span class="text-xs {{ !request('topic_id') ? 'text-white/80' : 'text-slate-400' }}">{{ $totalQuestions }}</span>
            </a>

            <div class="space-y-1 max-h-[28rem] lg:max-h-none overflow-y-auto overflow-x-hidden pr-1">
                @forelse ($folders as $folder)
                    @php $fCount = $folder->subjects->flatMap->topics->sum('questions_count'); @endphp
                    <details class="group" open>
                        <summary class="flex items-center justify-between gap-1 px-2 py-1.5 rounded-lg hover:bg-slate-50 cursor-pointer list-none">
                            <span class="flex items-center gap-1.5 min-w-0">
                                <svg class="w-3.5 h-3.5 text-slate-400 transition group-open:rotate-90 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M9 6l6 6-6 6"/></svg>
                                <svg class="w-4 h-4 text-amber-500 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M10 4H2v16h20V6H12z"/></svg>
                                <span class="font-semibold text-slate-700 text-sm truncate">{{ $folder->name }}</span>
                            </span>
                            <span class="flex items-center gap-1 shrink-0">
                                <span class="text-[11px] text-slate-400 bg-slate-100 rounded-full px-1.5">{{ $fCount }}</span>
                                <button type="button" onclick="event.preventDefault();renameNode('{{ route('folders.rename', $folder) }}','{{ e($folder->name) }}')" class="text-slate-300 hover:text-brand text-xs px-1">✎</button>
                                <button type="button" onclick="event.preventDefault();deleteNode('{{ route('folders.destroy', $folder) }}','folder {{ e($folder->name) }}')" class="text-slate-300 hover:text-rose-600 text-xs px-1">🗑</button>
                            </span>
                        </summary>
                        <div class="pl-4 mt-0.5 space-y-0.5">
                            @foreach ($folder->subjects as $subject)
                                @php $sCount = $subject->topics->sum('questions_count'); @endphp
                                <details class="group/s" open>
                                    <summary class="flex items-center justify-between gap-1 px-2 py-1 rounded-lg hover:bg-slate-50 cursor-pointer list-none">
                                        <span class="flex items-center gap-1.5 min-w-0">
                                            <svg class="w-3 h-3 text-slate-300 transition group-open/s:rotate-90 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M9 6l6 6-6 6"/></svg>
                                            <span class="text-slate-600 text-sm truncate">{{ $subject->name }}</span>
                                        </span>
                                        <span class="flex items-center gap-1 shrink-0">
                                            <span class="text-[11px] text-slate-400">{{ $sCount }}</span>
                                            <button type="button" onclick="event.preventDefault();renameNode('{{ route('subjects.rename', $subject) }}','{{ e($subject->name) }}')" class="text-slate-300 hover:text-brand text-xs px-1">✎</button>
                                            <button type="button" onclick="event.preventDefault();deleteNode('{{ route('subjects.destroy', $subject) }}','subject {{ e($subject->name) }}')" class="text-slate-300 hover:text-rose-600 text-xs px-1">🗑</button>
                                        </span>
                                    </summary>
                                    <div class="pl-5 mt-0.5 space-y-0.5">
                                        @foreach ($subject->topics as $topic)
                                            <div class="flex items-center justify-between gap-1 group/t rounded-lg {{ request('topic_id')==$topic->id ? 'bg-blue-50' : 'hover:bg-slate-50' }}">
                                                <a href="{{ route('questions.index', ['topic_id' => $topic->id]) }}" class="flex-1 min-w-0 flex items-center gap-1.5 px-2 py-1 text-sm {{ request('topic_id')==$topic->id ? 'text-brand font-semibold' : 'text-slate-500' }}">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-300 shrink-0"></span>
                                                    <span class="truncate">{{ $topic->name }}</span>
                                                </a>
                                                <span class="flex items-center gap-1 shrink-0 pr-1">
                                                    <span class="text-[11px] text-slate-400">{{ $topic->questions_count }}</span>
                                                    <button type="button" onclick="renameNode('{{ route('topics.rename', $topic) }}','{{ e($topic->name) }}')" class="text-slate-300 hover:text-brand text-xs px-0.5 opacity-0 group-hover/t:opacity-100">✎</button>
                                                    <button type="button" onclick="deleteNode('{{ route('topics.destroy', $topic) }}','topic {{ e($topic->name) }}')" class="text-slate-300 hover:text-rose-600 text-xs px-0.5 opacity-0 group-hover/t:opacity-100">🗑</button>
                                                </span>
                                            </div>
                                        @endforeach
                                        @if ($subject->topics->isEmpty())<p class="text-xs text-slate-300 px-2 py-1">No topics</p>@endif
                                    </div>
                                </details>
                            @endforeach
                            @if ($folder->subjects->isEmpty())<p class="text-xs text-slate-300 px-2 py-1">No subjects</p>@endif
                        </div>
                    </details>
                @empty
                    <p class="text-slate-400 text-sm py-3 text-center">No folders yet. Add one below.</p>
                @endforelse
            </div>
        </div>

        {{-- Add to hierarchy --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 space-y-3 text-sm">
            <div class="font-bold text-slate-800">➕ Add to hierarchy</div>
            <form method="POST" action="{{ route('folders.store') }}" class="flex gap-1">@csrf
                <input name="name" placeholder="New folder" required class="flex-1 min-w-0 rounded-lg border-slate-300 border px-2.5 py-1.5">
                <button class="bg-brand text-white px-3 rounded-lg font-medium">Add</button>
            </form>
            <form method="POST" action="{{ route('subjects.store') }}" class="space-y-1.5">@csrf
                <select name="folder_id" required class="w-full rounded-lg border-slate-300 border px-2.5 py-1.5">
                    <option value="">Folder…</option>
                    @foreach ($folders as $f)<option value="{{ $f->id }}">{{ $f->name }}</option>@endforeach
                </select>
                <div class="flex gap-1">
                    <input name="name" placeholder="New subject" required class="flex-1 min-w-0 rounded-lg border-slate-300 border px-2.5 py-1.5">
                    <button class="bg-brand text-white px-3 rounded-lg font-medium">Add</button>
                </div>
            </form>
            <form method="POST" action="{{ route('topics.store') }}" class="space-y-1.5">@csrf
                <select name="subject_id" required class="w-full rounded-lg border-slate-300 border px-2.5 py-1.5">
                    <option value="">Folder → Subject…</option>
                    @foreach ($folders as $f)@foreach ($f->subjects as $s)
                        <option value="{{ $s->id }}">{{ $f->name }} → {{ $s->name }}</option>
                    @endforeach @endforeach
                </select>
                <div class="flex gap-1">
                    <input name="name" placeholder="New topic" required class="flex-1 min-w-0 rounded-lg border-slate-300 border px-2.5 py-1.5">
                    <button class="bg-brand text-white px-3 rounded-lg font-medium">Add</button>
                </div>
            </form>
        </div>
    </aside>

    @push('scripts')
    <script>
        const CSRF = document.querySelector('meta[name=csrf-token]').content;
        function renameNode(action, current) {
            const name = prompt('Rename to:', current);
            if (name === null || !name.trim()) return;
            const f = document.createElement('form'); f.method = 'POST'; f.action = action;
            f.innerHTML = `<input name="_token" value="${CSRF}"><input name="_method" value="PATCH"><input name="name" value="${name.replace(/"/g,'&quot;')}">`;
            document.body.appendChild(f); f.submit();
        }
        function deleteNode(action, label) {
            if (!confirm('Delete ' + label + '? (must be empty of questions)')) return;
            const f = document.createElement('form'); f.method = 'POST'; f.action = action;
            f.innerHTML = `<input name="_token" value="${CSRF}"><input name="_method" value="DELETE">`;
            document.body.appendChild(f); f.submit();
        }
    </script>
    @endpush

    {{-- List + filters --}}
    <section class="lg:col-span-3 space-y-4 lg:flex lg:flex-col lg:min-h-0">
        <form method="GET" class="bg-white rounded-xl shadow-sm p-3 flex flex-wrap gap-2 text-sm shrink-0">
            <input name="search" value="{{ request('search') }}" placeholder="Search text…" class="flex-1 min-w-40 rounded border-slate-300 border px-3 py-1.5">
            <select name="type" class="rounded border-slate-300 border px-2 py-1.5">
                <option value="">All types</option>
                @foreach (['MCQ_SINGLE','MCQ_MULTI','MCQ_BLANKS','TRUE_FALSE','FILL_BLANK','NUMERIC','DESCRIPTIVE'] as $t)
                    <option value="{{ $t }}" @selected(request('type')===$t)>{{ str_replace('_',' ',$t) }}</option>
                @endforeach
            </select>
            <select name="difficulty" class="rounded border-slate-300 border px-2 py-1.5">
                <option value="">Any difficulty</option>
                @foreach (['EASY','MEDIUM','HARD'] as $d)<option value="{{ $d }}" @selected(request('difficulty')===$d)>{{ $d }}</option>@endforeach
            </select>
            <button class="bg-brand text-white px-3 rounded">Filter</button>
        </form>

        <div class="bg-white rounded-xl shadow-sm divide-y lg:flex-1 lg:min-h-0 lg:overflow-y-auto">
            @forelse ($questions as $q)
                <div class="p-4 flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 mb-1 text-xs">
                            <span class="px-2 py-0.5 rounded bg-brand/10 text-brand font-medium">{{ str_replace('_',' ',$q->type) }}</span>
                            @if ($q->difficulty)<span class="text-slate-400">{{ $q->difficulty }}</span>@endif
                            <x-status :value="$q->status" />
                            <span class="text-slate-400">{{ $q->marks }} mark(s)</span>
                            @if ($q->image_path)<span class="text-slate-400" title="Has an image">🖼</span>@endif
                        </div>
                        <p class="font-medium text-slate-800 truncate">{{ $q->text }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">{{ $q->category ?? '—' }} → {{ $q->subject ?? '—' }} → {{ $q->topic ?? '—' }}</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="{{ route('questions.edit', $q) }}" class="text-brand text-sm hover:underline">Edit</a>
                        <form method="POST" action="{{ route('questions.destroy', $q) }}" onsubmit="return confirm('Delete this question?')">
                            @csrf @method('DELETE')
                            <button class="text-rose-600 text-sm hover:underline">Delete</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="p-8 text-center text-slate-400">No questions found.</p>
            @endforelse
        </div>
        <div class="shrink-0">{{ $questions->links() }}</div>
    </section>
</div>
</div>
@endsection
