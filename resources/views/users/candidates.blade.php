@extends('layouts.app')
@section('title', 'Candidates')
@section('content')
<h1 class="text-2xl font-bold mb-1">Candidates <span class="text-slate-400 font-normal text-lg">· Students</span></h1>
<p class="text-slate-500 mb-5 text-sm">Students who take exams. (Staff accounts are under <a href="{{ route('users.index') }}" class="text-brand hover:underline">Users</a>.)</p>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1 bg-white rounded-2xl shadow-sm border border-slate-100 p-5 h-fit">
        <div class="font-semibold mb-3">Add candidate</div>
        <form method="POST" action="{{ route('candidates.store') }}" class="space-y-3 text-sm">@csrf
            <input name="name" placeholder="Full name" required class="w-full rounded-lg border-slate-300 border px-3 py-2">
            <input name="email" type="email" placeholder="Email" required class="w-full rounded-lg border-slate-300 border px-3 py-2">
            <input name="student_id" placeholder="Student ID / Roll no." class="w-full rounded-lg border-slate-300 border px-3 py-2">
            <input name="batch" placeholder="Batch / department (e.g. CSE-A)" class="w-full rounded-lg border-slate-300 border px-3 py-2">
            <input name="password" type="text" placeholder="Temp password (min 6)" required class="w-full rounded-lg border-slate-300 border px-3 py-2">
            <button class="w-full bg-brand text-white py-2 rounded-lg font-medium hover:bg-brand-dark">Add candidate</button>
        </form>
        <p class="text-[11px] text-slate-400 mt-3">Tip: for large batches, use a test's <b>public link</b> — students self-register, no need to add them here.</p>
    </div>

    <div class="lg:col-span-2">
        <form method="GET" class="flex flex-wrap gap-2 mb-3 text-sm">
            <input name="search" value="{{ request('search') }}" placeholder="Search name / email / ID" class="flex-1 min-w-40 rounded-lg border-slate-300 border px-3 py-2">
            <select name="batch" class="rounded-lg border-slate-300 border px-2">
                <option value="">All batches</option>
                @foreach ($batches as $b)<option value="{{ $b }}" @selected(request('batch')===$b)>{{ $b }}</option>@endforeach
            </select>
            <button class="bg-brand text-white px-4 rounded-lg">Go</button>
        </form>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr><th class="px-4 py-2">Candidate</th><th class="px-4 py-2">ID / Batch</th><th class="px-4 py-2">Assigned</th><th class="px-4 py-2">Attempts</th><th class="px-4 py-2">Active</th><th></th></tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($candidates as $c)
                        <tr>
                            <td class="px-4 py-2">{{ $c->name }}<div class="text-xs text-slate-400">{{ $c->email }}</div></td>
                            <td class="px-4 py-2 text-slate-500">{{ $c->student_id ?? '—' }} {{ $c->batch ? '· '.$c->batch : '' }}</td>
                            <td class="px-4 py-2">{{ $c->assignments_count }}</td>
                            <td class="px-4 py-2">{{ $c->attempts_count }}</td>
                            <td class="px-4 py-2">{!! $c->is_active ? '<span class="text-emerald-600">Yes</span>' : '<span class="text-rose-600">No</span>' !!}</td>
                            <td class="px-4 py-2 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <form method="POST" action="{{ route('users.toggle', $c) }}">@csrf<button class="text-brand hover:underline text-sm">{{ $c->is_active ? 'Disable' : 'Enable' }}</button></form>
                                    <form method="POST" action="{{ route('users.destroy', $c) }}" onsubmit="return confirm('Delete {{ $c->name }}?')">@csrf @method('DELETE')<button class="text-rose-600 hover:underline text-sm">Delete</button></form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No candidates yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $candidates->links() }}</div>
    </div>
</div>
@endsection
