@props(['size' => 'w-11 h-11', 'icon' => 'w-6 h-6'])
@php $logo = \App\Models\Setting::get('org_logo'); @endphp
@if ($logo)
    <img src="{{ $logo }}" alt="Logo" class="{{ $size }} rounded-xl object-contain bg-white border border-slate-100 shadow-sm p-0.5">
@else
    <div class="{{ $size }} rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center shadow">
        <svg class="{{ $icon }} text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3 1 9l11 6 9-4.91V17h2V9L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg>
    </div>
@endif
