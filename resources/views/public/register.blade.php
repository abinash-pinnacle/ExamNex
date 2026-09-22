@php
    $appName = \App\Models\Setting::get('org_name', config('app.name'));
    $mins = $test->duration_minutes;
    $marks = $test->total_marks;
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $test->title }} · {{ $appName }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Caveat:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
    <style>
        :root { --brand-rgb: {{ \App\Models\Setting::brandRgb() }}; --brand-dark-rgb: {{ \App\Models\Setting::brandDarkRgb() }}; }
        body { font-family: 'Inter', sans-serif; }
        .hand { font-family: 'Caveat', cursive; }
        .dotgrid { background-image: radial-gradient(rgba(255,255,255,.16) 1.4px, transparent 1.4px); background-size: 22px 22px; }
        .dotgrid-d { background-image: radial-gradient(rgba(37,99,235,.14) 1.4px, transparent 1.4px); background-size: 22px 22px; }
    </style>
</head>
<body class="h-full bg-[#eef3fb] text-slate-800">
<div class="app-shell flex flex-col overflow-hidden">

    {{-- ================= HEADER ================= --}}
    <header class="bg-white/90 backdrop-blur border-b border-slate-100 shrink-0">
        <div class="max-w-[1600px] mx-auto px-5 sm:px-8 h-20 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center shadow">
                    <svg class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3 1 9l11 6 9-4.91V17h2V9L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg>
                </div>
                <div class="leading-tight">
                    <div class="text-2xl font-extrabold tracking-tight">@if($appName==='ExamNex')Exam<span class="text-brand">Nex</span>@else{{ $appName }}@endif</div>
                    <div class="text-[11px] text-slate-400 font-medium">Online Testing Platform</div>
                </div>
            </div>
            <div class="flex items-center gap-5">
                <div class="hidden sm:block text-right leading-tight">
                    <div class="text-[10px] uppercase tracking-widest text-slate-400">A Product of</div>
                    <div class="text-sm font-bold text-slate-700">PinTek <span class="text-brand">Digital</span></div>
                </div>
                <div class="hand hidden md:block text-blue-300 text-lg leading-none text-right">Assess<br>Learn<br>Grow</div>
            </div>
        </div>
    </header>

    {{-- ================= MAIN ================= --}}
    <main class="flex-1 min-h-0 flex items-stretch overflow-hidden">

        {{-- ---- LEFT: brand / features ---- --}}
        <aside class="hidden lg:flex lg:w-[31%] xl:w-[33%] relative overflow-hidden flex-col justify-center px-8 xl:px-11 py-10 text-white"
               style="background: linear-gradient(150deg,#0b1e42 0%,#0e2a5e 55%,#12336e 100%);">
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute top-0 right-0 w-80 h-80 dotgrid opacity-60" style="mask-image:linear-gradient(to bottom left,black,transparent);"></div>
                <div class="absolute -bottom-24 -left-24 w-96 h-96 rounded-full" style="background:radial-gradient(circle,#1d4ed8 0%,transparent 70%);opacity:.35;"></div>
            </div>
            <div class="relative z-10 max-w-md">
                <div class="w-16 h-px bg-blue-400/70 mb-5"></div>
                <div class="text-blue-200/90 text-lg font-medium">Welcome to</div>
                <h1 class="text-5xl font-extrabold tracking-tight leading-none mt-1">Exam<span class="text-blue-400">Nex</span></h1>
                <p class="text-blue-200/80 mt-3 text-lg">Secure. Smart. Simple.</p>

                <div class="mt-9 space-y-5">
                    @php
                        $feat = [
                            ['M12 3 4 6v5c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V6z', 'Fair &amp; Secure', 'Trusted assessment environment'],
                            ['M4 13h3v7H4zm6.5-5h3v12h-3zM17 3h3v17h-3z', 'Easy &amp; Seamless', 'Simple and student-friendly'],
                            ['M12 7v5l3 2M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z', 'Anytime, Anywhere', 'Take tests on any device'],
                            ['M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6', 'Better Opportunities', 'Showcase your skills'],
                        ];
                    @endphp
                    @foreach ($feat as [$icon,$t,$d])
                        <div class="flex items-center gap-4">
                            <span class="w-12 h-12 rounded-2xl bg-white/10 border border-white/10 flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6 text-blue-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                            </span>
                            <div>
                                <div class="font-bold leading-tight">{!! $t !!}</div>
                                <div class="text-sm text-blue-200/70 leading-tight">{{ $d }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="hand text-blue-300/90 text-2xl mt-10 leading-none">Your Skills<br><span class="pl-8">Our Future</span></div>
            </div>
        </aside>

        {{-- ---- CENTER: registration form ---- --}}
        <div class="flex-1 min-h-0 overflow-y-auto">
          <div class="min-h-full flex items-center justify-center p-4 sm:p-8">
            <div class="w-full max-w-lg bg-white rounded-3xl shadow-xl border border-slate-100 p-6 sm:p-8">
                <div class="flex items-start gap-3 pb-5 border-b border-slate-100">
                    <span class="w-12 h-12 rounded-2xl bg-blue-50 text-brand flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 12h6M9 16h6M6 3h9l5 5v13H6z"/></svg>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-2xl font-extrabold text-slate-900 leading-tight truncate">{{ $test->title }}</h2>
                        <div class="flex items-center gap-3 text-sm text-slate-500 mt-1">
                            <span class="inline-flex items-center gap-1"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>{{ $mins }} min</span>
                            <span class="text-slate-300">&middot;</span>
                            <span class="inline-flex items-center gap-1"><svg class="w-4 h-4 text-amber-400" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.9 6.3 6.9.6-5.2 4.6 1.6 6.8L12 17.8 5.8 20.9l1.6-6.8L2.2 8.9l6.9-.6z"/></svg>{{ $marks }} marks</span>
                        </div>
                    </div>
                </div>

                <p class="text-sm text-slate-500 mt-4 mb-5">Enter your details to begin the assessment.</p>

                @if ($errors->any())
                    <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
                @endif

                @php
                    $fields = [
                        ['full_name', 'Full name', 'Enter your full name', true, 'text', 'M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0zM12 14c-4.4 0-8 2.2-8 5v1h16v-1c0-2.8-3.6-5-8-5z'],
                        ['student_id', 'Student ID / Roll number', 'Enter your student ID or roll number', true, 'text', 'M3 5h18v14H3zM7 9h4M7 13h8M15 9h2'],
                        ['contact', 'Email or mobile', 'Enter your email or mobile number', true, 'text', 'M4 4h16v16H4zM4 7l8 6 8-6'],
                        ['department', 'Department / batch (optional)', 'e.g. CSE / 2026', false, 'text', 'M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H4v-2a4 4 0 0 1 3-3.87M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
                    ];
                @endphp

                <form method="POST" action="{{ route('public.test', $code) }}" class="space-y-4">
                    @csrf
                    @foreach ($fields as [$name,$label,$ph,$req,$type,$icon])
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">{{ $label }} @if($req)<span class="text-rose-500">*</span>@endif</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 w-8 h-8 rounded-lg bg-slate-50 text-slate-400 flex items-center justify-center">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="{{ $icon }}"/></svg>
                                </span>
                                <input name="{{ $name }}" value="{{ old($name) }}" placeholder="{{ $ph }}" @if($req)required @endif
                                       class="w-full rounded-xl border border-slate-200 bg-slate-50/60 pl-12 pr-4 py-3 text-sm focus:bg-white focus:border-brand focus:ring-2 focus:ring-brand/30 outline-none transition">
                            </div>
                        </div>
                    @endforeach

                    @if ($test->access_password)
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Test password <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 w-8 h-8 rounded-lg bg-slate-50 text-slate-400 flex items-center justify-center">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                                </span>
                                <input name="access_password" type="password" required placeholder="Enter the password shared with you"
                                       class="w-full rounded-xl border border-slate-200 bg-slate-50/60 pl-12 pr-4 py-3 text-sm focus:bg-white focus:border-brand focus:ring-2 focus:ring-brand/30 outline-none transition">
                            </div>
                        </div>
                    @endif

                    <button class="w-full bg-brand hover:bg-brand-dark text-white font-semibold py-3.5 rounded-xl shadow-lg flex items-center justify-center gap-2 transition"
                            style="box-shadow:0 12px 22px -10px rgb(var(--brand-rgb)/.6)">
                        <span>&rarr;</span> Register &amp; Start <span>&rarr;</span>
                    </button>
                </form>

                <div class="mt-4 flex items-center gap-2 rounded-xl bg-slate-50 border border-slate-100 px-4 py-2.5 text-xs text-slate-500">
                    <svg class="w-4 h-4 text-brand shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                    Your information is secure and will only be used for this assessment.
                </div>
            </div>
          </div>
        </div>

        {{-- ---- RIGHT: decorative ---- --}}
        <aside class="hidden xl:flex xl:w-[28%] relative overflow-hidden items-center justify-center"
               style="background: linear-gradient(160deg,#dbeafe 0%,#eff6ff 50%,#f8fbff 100%);">
            <div class="absolute inset-0 dotgrid-d opacity-60" style="mask-image:linear-gradient(to top left,black,transparent);"></div>
            <div class="hand absolute top-10 right-10 text-blue-400/70 text-3xl leading-tight text-right">Small<br>Tests<br>Big<br>Opportunities</div>

            {{-- laptop illustration --}}
            <div class="relative z-10 w-64">
                <div class="rounded-t-xl bg-slate-800 p-2 shadow-2xl">
                    <div class="rounded-lg bg-gradient-to-br from-blue-500 to-blue-700 aspect-[16/10] flex items-center justify-center">
                        <svg class="w-14 h-14 text-white/90" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3 1 9l11 6 9-4.91V17h2V9L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg>
                    </div>
                </div>
                <div class="h-3 bg-slate-300 rounded-b-xl mx-[-14px] shadow-lg"></div>
                <div class="mt-6 mx-auto w-40 h-24 rounded-lg bg-white shadow-md border border-slate-100 rotate-3 p-3">
                    <div class="h-2 w-16 bg-slate-200 rounded"></div>
                    <div class="h-2 w-24 bg-slate-100 rounded mt-2"></div>
                    <div class="h-2 w-20 bg-slate-100 rounded mt-2"></div>
                </div>
            </div>

            <div class="hand absolute bottom-10 left-10 text-blue-400/70 text-3xl leading-tight">Prepare<br>Perform<br>Progress</div>
        </aside>
    </main>

    {{-- ================= FOOTER ================= --}}
    <footer class="bg-white border-t border-slate-100 shrink-0">
        <div class="max-w-[1600px] mx-auto px-5 sm:px-8 py-2.5 text-center text-[11px] text-slate-400">
            &copy; {{ date('Y') }} PinTek Digital. ExamNex is a product of PinTek Digital. All rights reserved.
        </div>
    </footer>
</div>
</body>
</html>
