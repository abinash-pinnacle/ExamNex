<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Submitted · {{ $test->title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
    <style>:root{--brand-rgb: {{ \App\Models\Setting::brandRgb() }};--brand-dark-rgb: {{ \App\Models\Setting::brandDarkRgb() }};}</style>
</head>
<body class="h-full bg-gradient-to-b from-[#eaf1fb] to-[#f4f8fd] text-slate-800">
<main class="min-h-screen flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-lg bg-white rounded-2xl shadow-sm border border-slate-100 p-8 text-center">
        @if ($attempt->terminated)
            <div class="w-16 h-16 mx-auto rounded-full bg-rose-100 flex items-center justify-center mb-4">
                <svg class="w-9 h-9 text-rose-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
            </div>
            <h1 class="text-2xl font-extrabold text-rose-700">Exam Terminated</h1>
            <p class="text-slate-600 mt-2">Your exam was terminated because you left the exam window / attempted malpractice. This has been recorded and reported to the administrator.</p>
        @else
            <div class="w-16 h-16 mx-auto rounded-full bg-emerald-100 flex items-center justify-center mb-4">
                <svg class="w-9 h-9 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900">Response Submitted</h1>
            @if ($test->completion_message)
                <p class="text-slate-600 mt-2">{{ $test->completion_message }}</p>
            @else
                <p class="text-slate-600 mt-2">Thank you, <b>{{ $attempt->candidate->name ?? 'candidate' }}</b>. Your response for <b>{{ $test->title }}</b> has been recorded successfully.</p>
            @endif
        @endif

        <div class="bg-slate-50 rounded-xl p-4 mt-6 text-sm text-slate-500">
            Your result will be reviewed and declared by the organisation. Results are not shown here.
        </div>

        <div class="text-xs text-slate-400 mt-6">Submitted on {{ optional($attempt->submitted_at)->format('d M Y, h:i A') }}</div>
        <a href="{{ route('candidate.home') }}" class="inline-block mt-6 text-brand font-medium hover:underline">← Back to my tests</a>
    </div>
</main>
</body>
</html>
