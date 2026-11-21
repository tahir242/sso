@props(['title', 'description' => null, 'icon' => null])

<section {{ $attributes->class('rounded-2xl bg-white shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800') }}>
    <header class="flex items-start gap-4 border-b border-zinc-100 px-6 py-5 dark:border-zinc-800">
        @if ($icon)
            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300">
                <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-5" />
            </span>
        @endif
        <div class="min-w-0 flex-1">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-white">{{ $title }}</h2>
            @if ($description)
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="shrink-0">{{ $actions }}</div>
        @endisset
    </header>
    <div class="px-6 py-5">
        {{ $slot }}
    </div>
</section>
