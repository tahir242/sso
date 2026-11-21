@props(['label', 'name', 'icon' => null, 'hint' => null, 'marked' => false])

<div {{ $attributes->only(['class', 'x-data']) }}>
    <label for="{{ $name }}" class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $label }}@if ($marked && $attributes->has('required'))<x-required />@endif</label>
    <div class="relative mt-2">
        @if ($icon)
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-zinc-400" />
        @endif
        <input
            id="{{ $name }}"
            @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
            {{ $attributes->except(['class', 'x-data', 'marked'])->merge(['type' => 'text'])->class([
                'block w-full rounded-xl border-0 bg-white py-3 pr-3 text-zinc-900 shadow-sm ring-1 ring-zinc-300 transition placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-primary-600 dark:bg-zinc-900 dark:text-white dark:ring-zinc-700 dark:focus:ring-primary-400 sm:text-sm',
                'pl-10' => $icon,
                'pl-3' => ! $icon,
            ]) }}
        >
        {{ $slot }}
    </div>
    @if ($hint)
        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $hint }}</p>
    @endif
    @error($name)
        <p id="{{ $name }}-error" class="mt-2 text-sm text-primary-700 dark:text-primary-400" role="alert">{{ $message }}</p>
    @enderror
</div>
