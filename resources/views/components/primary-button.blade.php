@props(['target' => null, 'loading' => null])

<button
    {{ $attributes->merge(['type' => 'submit'])->class('group relative flex w-full items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 disabled:cursor-wait disabled:opacity-70') }}
    @if ($target) wire:loading.attr="disabled" wire:target="{{ $target }}" @endif
>
    @if ($target && $loading)
        <span wire:loading.remove wire:target="{{ $target }}" class="flex items-center gap-2">{{ $slot }}</span>
        <span wire:loading wire:target="{{ $target }}">{{ $loading }}</span>
    @else
        {{ $slot }}
    @endif
</button>
