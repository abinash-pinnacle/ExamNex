@extends('layouts.app')
@section('title', 'Settings')
@section('content')
@php $isAdmin = auth()->user()->isAdmin(); @endphp

<h1 class="text-3xl font-extrabold text-slate-900 mb-1">Settings</h1>
<p class="text-slate-500 mb-6">Manage your organization, exam defaults and your account.</p>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    @if ($isAdmin)
    {{-- Organization --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
        <h2 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
            <span class="w-8 h-8 rounded-lg bg-blue-50 text-brand flex items-center justify-center">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M6 21V7l6-4 6 4v14M10 9h4M10 13h4M10 17h4"/></svg>
            </span> Organization
        </h2>
        <form method="POST" action="{{ route('settings.organization') }}" enctype="multipart/form-data" class="space-y-4">@csrf
            <div>
                <label class="block text-sm font-medium mb-1">Logo</label>
                <div class="flex items-center gap-3">
                    @php $curLogo = \App\Models\Setting::get('org_logo'); @endphp
                    <div class="w-14 h-14 rounded-xl border border-slate-200 bg-slate-50 flex items-center justify-center overflow-hidden shrink-0">
                        @if ($curLogo)
                            <img src="{{ $curLogo }}" alt="logo" class="w-full h-full object-contain p-1">
                        @else
                            <svg class="w-6 h-6 text-slate-300" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3 1 9l11 6 9-4.91V17h2V9L12 3z"/></svg>
                        @endif
                    </div>
                    <div>
                        <input type="file" name="logo" accept="image/*" class="block text-sm">
                        @if ($curLogo)
                            <label class="flex items-center gap-1.5 text-xs text-rose-600 mt-1"><input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300"> Remove current logo</label>
                        @endif
                        <p class="text-[11px] text-slate-400 mt-1">PNG/JPG/SVG, up to 2MB. Shown on exam & candidate pages.</p>
                    </div>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Name</label>
                <input name="org_name" value="{{ old('org_name', \App\Models\Setting::get('org_name')) }}" required class="w-full rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-brand outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Tagline</label>
                <input name="tagline" value="{{ old('tagline', \App\Models\Setting::get('tagline')) }}" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-brand outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Brand color</label>
                <div class="flex items-center gap-3">
                    <input type="color" name="brand_color" value="{{ old('brand_color', \App\Models\Setting::get('brand_color')) }}" class="h-11 w-16 rounded-lg border border-slate-200 cursor-pointer">
                    <span class="text-sm text-slate-500">Used across the app (buttons, active nav, links).</span>
                </div>
            </div>
            <div class="pt-2 border-t border-slate-100">
                <p class="text-sm font-medium text-slate-700 mt-3 mb-2">Candidate exam page — "Conducted by"</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <input name="conducted_by" value="{{ old('conducted_by', \App\Models\Setting::get('conducted_by')) }}" placeholder="Institution (e.g. NMIET B-SCHOOL)" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-brand outline-none">
                    <input name="conducted_by_sub" value="{{ old('conducted_by_sub', \App\Models\Setting::get('conducted_by_sub')) }}" placeholder="Sub-line (e.g. Placement Drive 2026)" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-brand outline-none">
                </div>
            </div>
            <button class="bg-brand text-white px-5 py-2.5 rounded-xl font-medium hover:bg-brand-dark">Save organization</button>
        </form>
    </div>

    {{-- Exam defaults --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
        <h2 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
            <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 2M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0z"/></svg>
            </span> Exam defaults
        </h2>
        <p class="text-xs text-slate-400 mb-4">Pre-filled when creating a new test.</p>
        <form method="POST" action="{{ route('settings.examDefaults') }}" class="space-y-4">@csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Duration (min)</label>
                    <input name="default_duration" type="number" min="1" value="{{ old('default_duration', \App\Models\Setting::get('default_duration')) }}" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-brand outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Passing marks</label>
                    <input name="default_passing" type="number" min="0" value="{{ old('default_passing', \App\Models\Setting::get('default_passing')) }}" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-brand outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Max attempts</label>
                    <input name="default_max_attempts" type="number" min="1" value="{{ old('default_max_attempts', \App\Models\Setting::get('default_max_attempts')) }}" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-brand outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Result visibility</label>
                    <select name="default_result_visibility" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-brand outline-none">
                        @foreach (['AFTER_REVIEW','IMMEDIATE','HIDDEN'] as $rv)
                            <option value="{{ $rv }}" @selected(old('default_result_visibility', \App\Models\Setting::get('default_result_visibility'))===$rv)>{{ str_replace('_',' ',$rv) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <button class="bg-brand text-white px-5 py-2.5 rounded-xl font-medium hover:bg-brand-dark">Save defaults</button>
        </form>
    </div>
    @endif

    {{-- Account --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 {{ $isAdmin ? 'lg:col-span-2' : '' }}">
        <h2 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
            <span class="w-8 h-8 rounded-lg bg-violet-50 text-violet-600 flex items-center justify-center">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0zM4 21v-1a6 6 0 0 1 12 0v1"/></svg>
            </span> Your account
        </h2>
        <form method="POST" action="{{ route('settings.account') }}" class="space-y-4 max-w-xl">@csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Name</label>
                    <input name="name" value="{{ old('name', auth()->user()->name) }}" required class="w-full rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-brand outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Email</label>
                    <input name="email" type="email" value="{{ old('email', auth()->user()->email) }}" required class="w-full rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-brand outline-none">
                </div>
            </div>
            <div class="pt-2 border-t border-slate-100">
                <p class="text-sm font-medium text-slate-700 mt-3 mb-2">Change password <span class="text-slate-400 font-normal">(leave blank to keep current)</span></p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <input name="current_password" type="password" placeholder="Current password" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-brand outline-none">
                    <input name="password" type="password" placeholder="New password" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-brand outline-none">
                    <input name="password_confirmation" type="password" placeholder="Confirm new password" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 focus:ring-2 focus:ring-brand outline-none">
                </div>
            </div>
            <button class="bg-brand text-white px-5 py-2.5 rounded-xl font-medium hover:bg-brand-dark">Save account</button>
        </form>
    </div>
</div>
@endsection
