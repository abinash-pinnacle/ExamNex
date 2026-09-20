@extends('layouts.app')
@section('title', 'Import Questions')
@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">Import Questions (Excel / CSV)</h1>
        <a href="{{ route('questions.template') }}" class="bg-emerald-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-emerald-700">⬇ Download Excel template</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6">
        <form method="POST" action="{{ route('questions.import') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium mb-1">Excel (.xlsx / .xls) or CSV file</label>
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="block w-full text-sm">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <input name="folder" placeholder="Default folder" class="rounded-lg border-slate-300 border px-3 py-2 text-sm">
                <input name="subject" placeholder="Default subject" class="rounded-lg border-slate-300 border px-3 py-2 text-sm">
                <input name="topic" placeholder="Default topic" class="rounded-lg border-slate-300 border px-3 py-2 text-sm">
            </div>
            <p class="text-xs text-slate-500">Defaults are used only for rows that don't specify folder/subject/topic columns.</p>
            <button class="bg-brand text-white px-5 py-2.5 rounded-lg font-medium hover:bg-brand-dark">Upload & Import</button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6 text-sm">
        <div class="font-semibold mb-2">Columns</div>
        <p class="text-slate-600 mb-2">Header row (any order): <code class="bg-slate-100 px-1 rounded">folder, subject, topic, type, question, option1..option8, correct, difficulty, marks, negative, tolerance, explanation, modelanswer</code></p>
        <ul class="list-disc list-inside text-slate-600 space-y-0.5">
            <li><b>type</b>: mcq / multi / truefalse / fill / numeric / descriptive</li>
            <li><b>correct</b>: MCQ → option number or letter (e.g. <code>2</code> or <code>B</code> or <code>1,3</code>); T/F → true/false; fill → accepted text; numeric → the number</li>
        </ul>
    </div>

    @if ($result)
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="font-semibold mb-3">Import result</div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center mb-4">
                <div class="bg-slate-50 rounded-lg p-3"><div class="text-2xl font-bold">{{ $result['total'] }}</div><div class="text-xs text-slate-500">rows</div></div>
                <div class="bg-emerald-50 rounded-lg p-3"><div class="text-2xl font-bold text-emerald-700">{{ $result['created'] }}</div><div class="text-xs text-slate-500">created</div></div>
                <div class="bg-amber-50 rounded-lg p-3"><div class="text-2xl font-bold text-amber-700">{{ $result['duplicates'] }}</div><div class="text-xs text-slate-500">duplicates</div></div>
                <div class="bg-rose-50 rounded-lg p-3"><div class="text-2xl font-bold text-rose-700">{{ $result['invalid'] }}</div><div class="text-xs text-slate-500">invalid</div></div>
            </div>
            @if (count($result['rejected']))
                <div class="overflow-x-auto"><table class="w-full text-sm min-w-[640px]">
                    <thead class="text-left text-slate-500"><tr><th class="py-1">Row</th><th>Kind</th><th>Reason</th></tr></thead>
                    <tbody class="divide-y">
                        @foreach ($result['rejected'] as $r)
                            <tr><td class="py-1">{{ $r['row'] }}</td><td>{{ $r['kind'] }}</td><td class="text-slate-600">{{ $r['reason'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table></div>
            @endif
        </div>
    @endif
</div>
@endsection
