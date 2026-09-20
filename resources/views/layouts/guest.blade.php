<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ExamNex')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
    <style>:root{--brand-rgb: {{ \App\Models\Setting::brandRgb() }};--brand-dark-rgb: {{ \App\Models\Setting::brandDarkRgb() }};}</style>
</head>
<body class="h-full bg-gradient-to-br from-brand-dark to-brand text-slate-800">
    <div class="min-h-full flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="text-center text-white mb-6">
                <h1 class="text-3xl font-bold tracking-tight">ExamNex</h1>
                <p class="opacity-80 text-sm mt-1">Online Testing Platform</p>
            </div>
            <div class="bg-white rounded-2xl shadow-xl p-6">
                @include('partials.flash')
                @yield('content')
            </div>
        </div>
    </div>
</body>
</html>
