@extends('layouts.app')
@section('title', 'AI Question Generator')
@section('content')
<div class="max-w-4xl mx-auto space-y-6 lg:h-[calc(100%_-_4rem)] lg:flex lg:flex-col lg:min-h-0">
    <h1 class="text-2xl font-bold shrink-0">✨ AI Question Generator</h1>
    <p class="text-sm text-slate-500 -mt-4 shrink-0">Drafts are reviewed by you, then saved through the same import pipeline (dedup + hierarchy apply).</p>

    <div class="lg:flex-1 lg:min-h-0 lg:overflow-y-auto lg:pr-1 space-y-6">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <form method="POST" action="{{ route('questions.ai.generate') }}" class="grid grid-cols-2 md:grid-cols-3 gap-4">
            @csrf
            <div class="col-span-2 md:col-span-3">
                <label class="block text-sm font-medium mb-1">Topic / prompt</label>
                <input name="topic" value="{{ old('topic') }}" required placeholder="e.g. Newton's laws of motion" class="w-full rounded-lg border-slate-300 border px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Type</label>
                <select name="type" class="w-full rounded-lg border-slate-300 border px-3 py-2">
                    <option value="MCQ_SINGLE">MCQ (single)</option>
                    <option value="TRUE_FALSE">True / False</option>
                    <option value="FILL_BLANK">Fill in the blank</option>
                    <option value="DESCRIPTIVE">Descriptive</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Count</label>
                <input name="count" type="number" min="1" max="50" value="{{ old('count', 5) }}" class="w-full rounded-lg border-slate-300 border px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Difficulty</label>
                <select name="difficulty" class="w-full rounded-lg border-slate-300 border px-3 py-2">
                    @foreach (['MIXED','EASY','MEDIUM','HARD'] as $d)<option value="{{ $d }}">{{ $d }}</option>@endforeach
                </select>
            </div>
            <div><label class="block text-sm font-medium mb-1">Folder</label><input name="folder" value="{{ old('folder') }}" required class="w-full rounded-lg border-slate-300 border px-3 py-2"></div>
            <div><label class="block text-sm font-medium mb-1">Subject</label><input name="subject" value="{{ old('subject') }}" required class="w-full rounded-lg border-slate-300 border px-3 py-2"></div>
            <div><label class="block text-sm font-medium mb-1">Topic (bank)</label><input name="topic_name" value="{{ old('topic_name') }}" required class="w-full rounded-lg border-slate-300 border px-3 py-2"></div>
            <div class="col-span-2 md:col-span-3">
                <button class="bg-brand text-white px-5 py-2.5 rounded-lg font-medium hover:bg-brand-dark">Generate</button>
            </div>
        </form>
    </div>

    @if ($generated)
        <form method="POST" action="{{ route('questions.ai.import') }}" class="bg-white rounded-xl shadow-sm p-6 space-y-4">
            @csrf
            <input type="hidden" name="questions" value="{{ json_encode($generated) }}">
            <input type="hidden" name="type" value="{{ $params['type'] }}">
            <input type="hidden" name="folder" value="{{ $params['folder'] }}">
            <input type="hidden" name="subject" value="{{ $params['subject'] }}">
            <input type="hidden" name="topic_name" value="{{ $params['topic_name'] }}">

            <div class="flex items-center justify-between">
                <div class="font-semibold">{{ count($generated) }} draft question(s)</div>
                <button class="bg-emerald-600 text-white px-5 py-2 rounded-lg font-medium hover:bg-emerald-700">Import all →</button>
            </div>
            <div class="text-xs text-slate-500">Will save to: {{ $params['folder'] }} → {{ $params['subject'] }} → {{ $params['topic_name'] }}</div>

            <div class="divide-y">
                @foreach ($generated as $g)
                    <div class="py-3">
                        <p class="font-medium">{{ $loop->iteration }}. {{ $g['text'] }}</p>
                        @if (!empty($g['options']))
                            <ul class="mt-1 text-sm text-slate-600 space-y-0.5">
                                @foreach ($g['options'] as $oi => $opt)
                                    <li class="{{ ($g['correctIndex'] ?? -1) === $oi ? 'text-emerald-700 font-medium' : '' }}">
                                        {{ chr(65+$oi) }}. {{ $opt }} {{ ($g['correctIndex'] ?? -1) === $oi ? '✓' : '' }}
                                    </li>
                                @endforeach
                            </ul>
                        @elseif (!empty($g['answer']))
                            <p class="text-sm text-emerald-700 mt-1">Answer: {{ $g['answer'] }}</p>
                        @endif
                        @if (!empty($g['explanation']))<p class="text-xs text-slate-400 mt-1">{{ $g['explanation'] }}</p>@endif
                    </div>
                @endforeach
            </div>
        </form>
    @endif
    </div>
</div>
@endsection
