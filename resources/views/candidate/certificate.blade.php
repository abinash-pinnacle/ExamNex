<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate · {{ $attempt->test->title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
    <style>:root{--brand-rgb: {{ \App\Models\Setting::brandRgb() }};--brand-dark-rgb: {{ \App\Models\Setting::brandDarkRgb() }};}</style>
    <style>@media print { .no-print { display:none } body { background:#fff } }</style>
</head>
<body class="bg-slate-100 p-6">
<div class="max-w-3xl mx-auto">
    <div class="no-print flex justify-between mb-4">
        <a href="{{ route('attempt.result', $attempt->id) }}" class="text-brand">← Back</a>
        <button onclick="print()" class="bg-brand text-white px-4 py-2 rounded-lg">Print / Save PDF</button>
    </div>
    <div class="bg-white border-8 border-brand/20 rounded-2xl p-12 text-center shadow-lg">
        <div class="text-brand font-bold text-lg tracking-widest">EXAMNEX</div>
        <h1 class="text-3xl font-serif font-bold mt-6 mb-2">Certificate of Achievement</h1>
        <p class="text-slate-500">This is to certify that</p>
        <p class="text-2xl font-semibold my-4">{{ $attempt->candidate->name }}</p>
        <p class="text-slate-500">has successfully passed</p>
        <p class="text-xl font-medium my-3">{{ $attempt->test->title }}</p>
        <p class="text-slate-600">with a score of <b>{{ rtrim(rtrim(number_format($attempt->total_score,2),'0'),'.') }} / {{ $attempt->max_score ?? $attempt->test->total_marks }}</b></p>
        <div class="mt-10 flex justify-between text-sm text-slate-500">
            <div>{{ optional($attempt->submitted_at)->format('d F Y') }}</div>
            <div class="border-t border-slate-300 pt-1">Authorised Signature</div>
        </div>
    </div>
</div>
</body>
</html>
