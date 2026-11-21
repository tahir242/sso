@props(['active' => 'access'])

@php
    $counts = \App\Domain\Access\Support\Inbox::openCounts(auth()->user());
    $tabs = [
        'access' => ['label' => 'Access', 'url' => route('access.requests'), 'icon' => 'heroicon-o-key', 'count' => $counts['access']],
        'help' => ['label' => 'IT help', 'url' => route('support'), 'icon' => 'heroicon-o-lifebuoy', 'count' => $counts['help']],
        'suggestions' => ['label' => 'Suggestions', 'url' => route('access.requests', ['tab' => 'changes']), 'icon' => 'heroicon-o-light-bulb', 'count' => $counts['suggestions']],
    ];
@endphp

{{-- Shared top of the three "My requests" lists: access, IT help tickets, suggestions. --}}
<div {{ $attributes }}>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">My requests</h1>
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Everything you asked for, and where it stands.</p>
        </div>
        <a href="{{ route('requests.new') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">
            <x-heroicon-o-plus class="size-4" /> New request
        </a>
    </div>

    <nav class="mt-6 flex gap-1 overflow-x-auto border-b border-zinc-200 dark:border-zinc-800" aria-label="My requests">
        @foreach ($tabs as $key => $tab)
            <a href="{{ $tab['url'] }}" @class([
                '-mb-px inline-flex shrink-0 items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium transition',
                'border-primary-600 text-primary-700 dark:text-primary-400' => $active === $key,
                'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $active !== $key,
            ]) @if ($active === $key) aria-current="page" @endif>
                <x-dynamic-component :component="$tab['icon']" class="size-4" />
                {{ $tab['label'] }}
                @if ($tab['count'])
                    <span class="rounded-full bg-zinc-200 px-1.5 text-xs font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200" title="{{ $tab['count'] }} open">{{ $tab['count'] }}</span>
                @endif
            </a>
        @endforeach
    </nav>
</div>
