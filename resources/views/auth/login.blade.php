<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
    <style>
        :root { --brand-rgb: {{ \App\Models\Setting::brandRgb() }}; --brand-dark-rgb: {{ \App\Models\Setting::brandDarkRgb() }}; }
        body { font-family: 'Inter', sans-serif; }
        .dotgrid {
            background-image: radial-gradient(rgba(255,255,255,.16) 1.4px, transparent 1.4px);
            background-size: 22px 22px;
        }
    </style>
</head>
<body class="h-full bg-white text-slate-800">
<div class="min-h-screen grid lg:grid-cols-2">

    {{-- ============ LEFT — brand / marketing ============ --}}
    <div class="relative hidden lg:flex flex-col justify-between overflow-hidden px-12 py-10 text-white"
         style="background: linear-gradient(135deg, #0b1e42 0%, #0e2a5e 55%, #12336e 100%);">

        {{-- decorative shapes --}}
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute top-0 right-0 w-96 h-96 dotgrid opacity-70" style="mask-image:linear-gradient(to bottom left,black,transparent);"></div>
            <div class="absolute -bottom-24 -left-20 w-96 h-96 rounded-full" style="background:radial-gradient(circle,#1d4ed8 0%,transparent 70%);opacity:.35;"></div>
            <div class="absolute top-1/3 -right-16 w-72 h-72 rotate-45" style="background:linear-gradient(135deg,rgba(59,130,246,.25),transparent);"></div>
        </div>

        {{-- logo --}}
        <div class="relative z-10">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center shadow-lg">
                    <svg class="w-9 h-9 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3 1 9l11 6 9-4.91V17h2V9L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg>
                </div>
                <div>
                    <div class="text-4xl font-extrabold leading-none tracking-tight">Exam<span class="text-blue-400">Nex</span></div>
                    <div class="mt-1 text-sm text-blue-200 tracking-wide font-medium">Assess &nbsp;•&nbsp; Analyze &nbsp;•&nbsp; Advance</div>
                </div>
            </div>
            <div class="mt-4 h-px w-64 bg-gradient-to-r from-blue-400/60 to-transparent"></div>
        </div>

        {{-- headline + features --}}
        <div class="relative z-10 max-w-lg">
            <h1 class="text-4xl xl:text-5xl font-extrabold leading-tight">Smarter Assessments<br>for a Brighter Tomorrow</h1>
            <p class="mt-5 text-blue-100/80 text-lg leading-relaxed">A modern online testing platform for organizations, institutions and recruitment drives.</p>

            <div class="mt-8 grid grid-cols-4 gap-4">
                @php
                    $features = [
                        ['from-indigo-500 to-indigo-700', 'Secure', 'Exams', '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6" fill="none" stroke="white" stroke-width="1.6"/>'],
                        ['from-emerald-500 to-emerald-700', 'Scalable', 'for Large Batches', '<path d="M16 11a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm-8 0a3 3 0 1 0-3-3 3 3 0 0 0 3 3zm0 2c-2.7 0-8 1.34-8 4v3h9v-3c0-1 .4-1.9 1.1-2.6C9.3 13.1 8.5 13 8 13zm8 0c-.3 0-.7 0-1.1.05C15.6 14 16 15 16 16v3h8v-3c0-2.66-5.3-4-8-4z"/>'],
                        ['from-orange-500 to-orange-600', 'Real-time', 'Analytics', '<path d="M4 13h3v7H4zm6.5-5h3v12h-3zM17 3h3v17h-3z"/>'],
                        ['from-blue-500 to-blue-700', 'Reliable', '& Easy to Use', '<path d="M12 2 4 5v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V5z"/>'],
                    ];
                @endphp
                @foreach ($features as [$grad, $l1, $l2, $svg])
                    <div class="text-center">
                        <div class="mx-auto w-14 h-14 rounded-2xl bg-gradient-to-br {{ $grad }} flex items-center justify-center shadow-md">
                            <svg class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="currentColor">{!! $svg !!}</svg>
                        </div>
                        <div class="mt-2 text-sm font-semibold leading-tight">{{ $l1 }}<br><span class="font-normal text-blue-100/80">{{ $l2 }}</span></div>
                    </div>
                @endforeach
            </div>

            <div class="mt-9 pl-5 border-l-2 border-blue-400/50">
                <p class="italic text-blue-100/80 text-lg leading-relaxed">Empowering Education &amp; Opportunities Through Technology</p>
            </div>
        </div>

        <div class="relative z-10 text-xs text-blue-200/60"><x-copyright class="hover:text-white" /></div>
    </div>

    {{-- ============ RIGHT — sign-in form ============ --}}
    <div class="relative flex items-center justify-center px-6 py-12 bg-white">
        <a href="mailto:support@examnex.test" class="absolute top-6 right-6 flex items-center gap-1.5 text-sm text-slate-500 hover:text-brand">
            <svg class="w-5 h-5 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 1 1 5 2.2c-.9.8-1.6 1.3-1.6 2.3"/><line x1="12" y1="17" x2="12" y2="17"/></svg>
            Need Help?
        </a>

        <div class="w-full max-w-md">
            @php $appNameM = \App\Models\Setting::get('org_name', config('app.name')); @endphp
            <div class="lg:hidden flex flex-col items-center gap-2.5 mb-6">
                <x-brand-logo size="w-14 h-14" icon="w-8 h-8" />
                <div class="text-2xl font-extrabold tracking-tight text-slate-900">
                    @if ($appNameM === 'ExamNex')Exam<span class="text-brand">Nex</span>@else{{ $appNameM }}@endif
                </div>
            </div>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 text-center">Welcome Back</h2>
            <p class="mt-2 text-center text-slate-500">Sign in to access your ExamNex account</p>

            @if ($errors->any())
                <div class="mt-6 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
                @csrf

                {{-- Email --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Email Address</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        </span>
                        <input name="email" type="email" value="{{ old('email') }}" required autofocus placeholder="you@example.com"
                               class="w-full rounded-xl border border-slate-200 bg-slate-50 pl-11 pr-4 py-3 focus:bg-white focus:ring-2 focus:ring-brand focus:border-brand outline-none transition">
                    </div>
                </div>

                {{-- Password --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        </span>
                        <input name="password" id="password" type="password" required placeholder="••••••••"
                               class="w-full rounded-xl border border-slate-200 bg-slate-50 pl-11 pr-11 py-3 focus:bg-white focus:ring-2 focus:ring-brand focus:border-brand outline-none transition">
                        <button type="button" id="togglePw" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600">
                            <svg id="eyeIcon" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="mt-2 text-right">
                        <a href="#" class="text-sm font-medium text-brand hover:underline">Forgot password?</a>
                    </div>
                </div>

                {{-- Remember --}}
                <label class="flex items-center gap-2.5 text-sm text-slate-600 select-none">
                    <input type="checkbox" name="remember" checked class="w-4 h-4 rounded border-slate-300 text-brand focus:ring-brand">
                    Remember me
                </label>

                {{-- Submit --}}
                <button class="w-full bg-brand hover:bg-blue-700 text-white font-semibold py-3.5 rounded-xl shadow-lg shadow-blue-500/30 transition flex items-center justify-center gap-2">
                    Sign in
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
            </form>

            <p class="mt-8 text-center text-sm text-slate-500">
                Don't have an account? <a href="mailto:support@examnex.test" class="font-semibold text-brand hover:underline">Contact your administrator.</a>
            </p>
        </div>

    </div>
</div>

<script>
    const btn = document.getElementById('togglePw');
    const pw = document.getElementById('password');
    const eye = document.getElementById('eyeIcon');
    btn.addEventListener('click', () => {
        const show = pw.type === 'password';
        pw.type = show ? 'text' : 'password';
        eye.innerHTML = show
            ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-6.5 0-10-7-10-7a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c6.5 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22" /><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />'
            : '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>';
    });
</script>
</body>
</html>
