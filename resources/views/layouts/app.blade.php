@php
    $appName = \App\Models\Setting::get('org_name', config('app.name'));
    $tagline = \App\Models\Setting::get('tagline', 'Test • Learn • Grow');
    $brand   = \App\Models\Setting::get('brand_color', '#2563eb');
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ $appName }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>:root{--brand-rgb: {{ \App\Models\Setting::brandRgb() }};--brand-dark-rgb: {{ \App\Models\Setting::brandDarkRgb() }};}</style>
</head>
<body class="h-full bg-[#f4f7fb] text-slate-800">
@php
    $u = auth()->user();
    $nav = [
        ['Dashboard', route('dashboard'), request()->is('dashboard'), 'M3 12l9-9 9 9M5 10v10a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V10'],
        ['Questions', route('questions.index'), request()->is('questions*'), 'M9 12h6M9 16h6M9 8h6M5 3h14a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z'],
        ['Tests', route('tests.index'), request()->is('tests*'), 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-6 9l2 2 4-4'],
        ['Candidates', route('candidates.index'), request()->is('candidates*'), 'M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H4v-2a4 4 0 0 1 3-3.87m6-1.13a4 4 0 1 0-4-4 4 4 0 0 0 4 4z'],
        ['Grading', route('grading.index'), request()->is('grading*'), 'M9 17v-6M12 17v-10M15 17v-3M4 4h16v16H4z'],
        ['Reports', route('reports.index'), request()->is('reports*'), 'M4 19V5m0 14h16M8 15l3-4 3 2 4-6'],
        ['Users', route('users.index'), request()->is('users*'), 'M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0zM12 14c-4.4 0-8 2.2-8 5v1h16v-1c0-2.8-3.6-5-8-5z'],
    ];
@endphp

<div class="h-screen overflow-hidden flex">
    {{-- ===== SIDEBAR ===== --}}
    <aside id="sidebar" class="fixed lg:static z-40 inset-y-0 left-0 w-64 bg-white border-r border-slate-200 flex flex-col -translate-x-full lg:translate-x-0 transition-transform">
        <div class="h-20 flex items-center gap-3 px-6 border-b border-slate-100">
            <x-brand-logo size="w-11 h-11" icon="w-6 h-6" />
            <div>
                <div class="text-xl font-extrabold tracking-tight leading-none">
                    @if ($appName === 'ExamNex')Exam<span class="text-brand">Nex</span>@else{{ $appName }}@endif
                </div>
                <div class="text-[11px] text-slate-400 font-medium mt-0.5">{{ $tagline }}</div>
            </div>
        </div>

        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
            @foreach ($nav as [$label, $href, $active, $icon])
                <a href="{{ $href }}"
                   class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition
                          {{ $active ? 'bg-brand text-white shadow-md shadow-blue-500/30' : 'text-slate-600 hover:bg-slate-100' }}">
                    <svg class="w-5 h-5 {{ $active ? 'text-white' : 'text-slate-400' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                    {{ $label }}
                </a>
            @endforeach
            @php $settingsActive = request()->is('settings') || request()->is('settings/*'); @endphp
            <a href="{{ route('settings.index') }}"
               class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition {{ $settingsActive ? 'bg-brand text-white shadow-md shadow-blue-500/30' : 'text-slate-600 hover:bg-slate-100' }}">
                <svg class="w-5 h-5 {{ $settingsActive ? 'text-white' : 'text-slate-400' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 7 19.4a1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0-1.1-2.7H1a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 2.6 7a1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H7a1.6 1.6 0 0 0 1-1.5V1a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V7a1.6 1.6 0 0 0 1.5 1H23a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/></svg>
                Settings
            </a>
        </nav>

        <div class="p-4">
            <div class="relative overflow-hidden rounded-2xl p-4 text-white bg-gradient-to-br from-blue-500 to-blue-700">
                <svg class="w-8 h-8 mb-2 opacity-90" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2H6v2H3v3a4 4 0 0 0 4 4 5 5 0 0 0 4 3.9V18H8v2h8v-2h-3v-3.1A5 5 0 0 0 17 11a4 4 0 0 0 4-4V4h-3V2zM5 7V6h1v3a2 2 0 0 1-1-2zm14 0a2 2 0 0 1-1 2V6h1v1z"/></svg>
                <div class="font-bold leading-tight">Better Assessments<br>Brighter Futures</div>
                <a href="{{ route('tests.create') }}" class="mt-3 inline-flex items-center justify-center w-8 h-8 rounded-full bg-white/20 hover:bg-white/30">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
            </div>
        </div>
    </aside>
    <div id="overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/30 z-30 hidden lg:hidden"></div>

    {{-- ===== MAIN ===== --}}
    <div class="flex-1 min-w-0 flex flex-col overflow-hidden">
        {{-- Topbar --}}
        <header class="h-16 shrink-0 bg-white border-b border-slate-200 flex items-center gap-4 px-4 lg:px-8 z-20">
            <button onclick="toggleSidebar()" class="p-2 rounded-lg hover:bg-slate-100 text-slate-500">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <form action="{{ route('questions.index') }}" method="GET" class="flex-1 max-w-xl relative">
                <svg class="w-5 h-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <input name="search" placeholder="Search candidates, tests, questions..."
                       class="w-full bg-slate-100 rounded-xl pl-11 pr-16 py-2.5 text-sm focus:bg-white focus:ring-2 focus:ring-brand outline-none">
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] text-slate-400 bg-white border border-slate-200 rounded px-1.5 py-0.5">Ctrl + K</span>
            </form>
            <div class="ml-auto flex items-center gap-3">
                <a href="{{ route('grading.index') }}" class="relative p-2 rounded-lg hover:bg-slate-100 text-slate-500">
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                    @if ($pendingGrading ?? 0)
                        <span class="absolute top-1 right-1 w-4 h-4 bg-rose-500 text-white text-[10px] rounded-full flex items-center justify-center">{{ $pendingGrading }}</span>
                    @endif
                </a>
                <div class="relative">
                    <button onclick="document.getElementById('userMenu').classList.toggle('hidden')" class="flex items-center gap-2.5 pl-1 pr-2 py-1 rounded-xl hover:bg-slate-100">
                        <span class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-500 to-blue-700 text-white text-sm font-bold flex items-center justify-center">{{ strtoupper(substr($u->name,0,2)) }}</span>
                        <span class="hidden sm:block text-left leading-tight">
                            <span class="block text-sm font-semibold">{{ $u->name }}</span>
                            <span class="block text-xs text-slate-400">{{ ucwords(strtolower(str_replace('_',' ',$u->role))) }}</span>
                        </span>
                        <svg class="w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div id="userMenu" class="hidden absolute right-0 mt-2 w-44 bg-white rounded-xl shadow-lg border border-slate-100 py-1 z-30">
                        <div class="px-4 py-2 text-xs text-slate-400 border-b border-slate-100">{{ $u->email }}</div>
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button class="w-full text-left px-4 py-2 text-sm text-rose-600 hover:bg-slate-50">Logout</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 lg:p-8">
            @include('partials.flash')
            @yield('content')

            <footer class="mt-8 pt-4 flex items-center justify-between text-xs text-slate-400 border-t border-slate-200">
                <span>© {{ date('Y') }} PinTek Digital. ExamNex is a product of PinTek Digital. All rights reserved.</span>
                <span class="flex gap-4"><a href="#" class="hover:text-slate-600">Privacy</a><a href="#" class="hover:text-slate-600">Terms</a><a href="mailto:support@examnex.test" class="hover:text-slate-600">Support</a></span>
            </footer>
        </main>
    </div>
</div>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('-translate-x-full');
        document.getElementById('overlay').classList.toggle('hidden');
    }
    document.addEventListener('click', e => {
        const m = document.getElementById('userMenu');
        if (m && !m.classList.contains('hidden') && !e.target.closest('.relative')) m.classList.add('hidden');
    });
    document.addEventListener('keydown', e => {
        if ((e.ctrlKey||e.metaKey) && e.key.toLowerCase()==='k') { e.preventDefault(); document.querySelector('input[name=search]')?.focus(); }
    });
</script>
@stack('scripts')
</body>
</html>
