@extends('layouts.app')
@section('title', 'Reports')
@section('content')
<h1 class="text-2xl font-bold mb-5">Reports</h1>
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-left">
            <tr><th class="px-4 py-2">Test</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Attempts</th><th class="px-4 py-2"></th></tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($tests as $t)
                <tr>
                    <td class="px-4 py-2 font-medium">{{ $t->title }}</td>
                    <td class="px-4 py-2"><x-status :value="$t->status" /></td>
                    <td class="px-4 py-2">{{ $t->attempts_count }}</td>
                    <td class="px-4 py-2 text-right">
                        <a href="{{ route('reports.show', $t) }}" class="text-brand hover:underline">View</a>
                        <a href="{{ route('reports.export', $t) }}" class="ml-3 text-brand hover:underline">Export Excel</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">No tests with reports yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
