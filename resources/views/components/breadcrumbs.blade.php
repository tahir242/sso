@props(['items' => []])

{{-- Breadcrumb trail: [[label, url|null], ...]; the last item is the current page. --}}
<nav aria-label="Breadcrumb" {{ $attributes->class('text-sm') }}>
    <ol class="flex flex-wrap items-center gap-1.5 text-slate-500 dark:text-zinc-400">
        @foreach ($items as [$label, $url])
            <li class="flex items-center gap-1.5">
                @if (! $loop->first)
                    <x-heroicon-m-chevron-right class="size-4 text-slate-300 dark:text-zinc-600" aria-hidden="true" />
                @endif
                @if ($url && ! $loop->last)
                    <a href="{{ $url }}" class="inline-flex items-center gap-1 rounded hover:text-slate-900 dark:hover:text-white">
                        @if ($loop->first)<x-heroicon-m-home class="size-4" aria-hidden="true" />@endif {{ $label }}
                    </a>
                @else
                    <span @if ($loop->last) aria-current="page" class="font-medium text-slate-900 dark:text-white" @endif>{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
