@props(['name', 'label', 'checked' => false, 'disabled' => false, 'note' => null, 'pro' => null, 'onToggle' => null])
<label class="flex items-center gap-2 {{ $disabled ? 'opacity-60' : 'cursor-pointer' }}">
    <input type="checkbox" name="{{ $name }}" value="1"
           @checked($checked) @disabled($disabled)
           @if($onToggle) onchange="{{ $onToggle }}(this)" @endif
           class="w-4 h-4 rounded border-slate-300 shrink-0" style="accent-color:rgb(var(--brand-rgb))">
    <span class="text-slate-600 leading-tight">{{ $label }}</span>
    @if($pro)<span class="text-[10px] bg-violet-100 text-violet-700 px-1.5 rounded font-semibold">{{ $pro }}</span>@endif
    @if($note)<span class="text-[10px] text-slate-400">({{ $note }})</span>@endif
</label>
