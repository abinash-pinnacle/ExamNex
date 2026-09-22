@extends('layouts.app')
@section('title', 'Users (Staff)')
@section('content')
<div class="lg:h-[calc(100%_-_4rem)] lg:flex lg:flex-col lg:min-h-0">
<h1 class="text-2xl font-bold mb-1 shrink-0">Users <span class="text-slate-400 font-normal text-lg">· Staff</span></h1>
<div class="lg:flex-1 lg:min-h-0 lg:overflow-y-auto lg:pr-1">
<p class="text-slate-500 mb-5 text-sm">Admins and Test Creators who manage the platform. (Students are under <a href="{{ route('candidates.index') }}" class="text-brand hover:underline">Candidates</a>.)</p>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1 bg-white rounded-2xl shadow-sm border border-slate-100 p-5 h-fit">
        <div class="font-semibold mb-3">Add staff member</div>
        @if (auth()->user()->isAdmin())
            <form method="POST" action="{{ route('users.store') }}" class="space-y-3 text-sm">@csrf
                <input name="name" placeholder="Full name" required class="w-full rounded-lg border-slate-300 border px-3 py-2">
                <input name="email" type="email" placeholder="Email" required class="w-full rounded-lg border-slate-300 border px-3 py-2">
                <select name="role" class="w-full rounded-lg border-slate-300 border px-3 py-2">
                    <option value="TEST_CREATOR">Test Creator</option>
                    <option value="ADMIN">Admin</option>
                </select>
                <input name="password" type="text" placeholder="Temp password (min 6)" required class="w-full rounded-lg border-slate-300 border px-3 py-2">
                <button class="w-full bg-brand text-white py-2 rounded-lg font-medium hover:bg-brand-dark">Create staff</button>
            </form>
        @else
            <p class="text-sm text-slate-400">Only an admin can add or manage staff.</p>
        @endif
    </div>

    <div class="lg:col-span-2">
        <form method="GET" class="flex gap-2 mb-3 text-sm">
            <input name="search" value="{{ request('search') }}" placeholder="Search name / email" class="flex-1 rounded-lg border-slate-300 border px-3 py-2">
            <button class="bg-brand text-white px-4 rounded-lg">Go</button>
        </form>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto"><table class="w-full text-sm min-w-[640px]">
                <thead class="bg-slate-50 text-slate-500 text-left"><tr><th class="px-4 py-2">Name</th><th class="px-4 py-2">Role</th><th class="px-4 py-2">Active</th><th></th></tr></thead>
                <tbody class="divide-y">
                    @forelse ($users as $u)
                        <tr>
                            <td class="px-4 py-2">{{ $u->name }}<div class="text-xs text-slate-400">{{ $u->email }}</div></td>
                            <td class="px-4 py-2"><span class="text-xs bg-slate-100 px-2 py-0.5 rounded">{{ $u->role }}</span></td>
                            <td class="px-4 py-2">{!! $u->is_active ? '<span class="text-emerald-600">Yes</span>' : '<span class="text-rose-600">No</span>' !!}</td>
                            <td class="px-4 py-2 text-right">
                                @if ($u->id !== auth()->id())
                                    <div class="flex items-center justify-end gap-3">
                                        <form method="POST" action="{{ route('users.toggle', $u) }}">@csrf<button class="text-brand hover:underline text-sm">{{ $u->is_active ? 'Disable' : 'Enable' }}</button></form>
                                        <form method="POST" action="{{ route('users.destroy', $u) }}" onsubmit="return confirm('Delete {{ $u->name }}?')">@csrf @method('DELETE')<button class="text-rose-600 hover:underline text-sm">Delete</button></form>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400">You</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">No staff accounts.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
        </div>
        <div class="mt-3">{{ $users->links() }}</div>
    </div>
</div>
</div>
</div>
@endsection
