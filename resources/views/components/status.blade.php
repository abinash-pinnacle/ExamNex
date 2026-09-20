@props(['value'])
@php
    $map = [
        'DRAFT' => 'bg-slate-100 text-slate-600',
        'PUBLISHED' => 'bg-emerald-100 text-emerald-700',
        'ARCHIVED' => 'bg-slate-200 text-slate-500',
        'ACTIVE' => 'bg-emerald-100 text-emerald-700',
        'IN_PROGRESS' => 'bg-amber-100 text-amber-700',
        'SUBMITTED' => 'bg-sky-100 text-sky-700',
        'AUTO_SUBMITTED' => 'bg-sky-100 text-sky-700',
        'EVALUATED' => 'bg-emerald-100 text-emerald-700',
    ];
    $cls = $map[$value] ?? 'bg-slate-100 text-slate-600';
@endphp
<span {{ $attributes->merge(['class' => "inline-block px-2 py-0.5 rounded-full text-xs font-medium $cls"]) }}>{{ str_replace('_', ' ', $value) }}</span>
