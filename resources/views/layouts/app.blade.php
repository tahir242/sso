<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @include('partials.favicons')
    <x-theme-script />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-zinc-50 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    @auth
        @php
            $me = auth()->user();
            $nav = [
                ['route' => 'home', 'match' => ['home'], 'label' => 'My applications'],
                // Everything the user asked for: access, IT help, suggestions.
                ['route' => 'access.requests', 'match' => ['access.*', 'support*', 'requests.*', 'suggestions.*'], 'label' => 'My requests', 'count' => \App\Domain\Access\Support\Inbox::ticketsWaiting($me)],
            ];
            // Only people who approve something see Approvals.
            if (\App\Domain\Access\Support\Inbox::isApprover($me)) {
                $nav[] = ['route' => 'approvals', 'match' => ['approvals*'], 'label' => 'Approvals', 'count' => \App\Domain\Access\Support\Inbox::approvalsWaiting($me)];
            }
            $nav[] = ['route' => 'account.security', 'match' => ['account.*'], 'label' => 'My account'];
        @endphp
    @endauth

    <header class="sticky top-0 z-30 border-b border-zinc-200/80 bg-white/80 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/80">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="shrink-0"><x-brand-mark /></a>

            @auth
                <nav class="hidden items-center gap-1 md:flex" aria-label="Main">
                    @foreach ($nav as $item)
                        <a href="{{ route($item['route']) }}" @class([
                            'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition hover:bg-zinc-100 dark:hover:bg-zinc-800',
                            'text-primary-700 dark:text-primary-400' => request()->routeIs(...$item['match']),
                            'text-zinc-700 dark:text-zinc-300' => ! request()->routeIs(...$item['match']),
                        ])>
                            {{ $item['label'] }}
                            @if (! empty($item['count']))
                                <span class="rounded-full bg-primary-600 px-1.5 text-xs font-semibold text-white">{{ $item['count'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </nav>
            @endauth

            <div class="flex items-center gap-1">
                <x-theme-toggle />
                @auth
                    <livewire:notification-bell />
                    @if (auth()->user()->canAccessPanel(filament()->getDefaultPanel()))
                        <a href="/admin" class="hidden rounded-lg px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 lg:inline-flex dark:text-zinc-300 dark:hover:bg-zinc-800">Admin console</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-lg px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">Sign out</button>
                    </form>
                @endauth
            </div>
        </div>

        @auth
            {{-- Compact navigation on small screens --}}
            <nav class="flex gap-1 overflow-x-auto border-t border-zinc-200/80 px-4 py-2 md:hidden dark:border-zinc-800" aria-label="Main">
                @foreach ($nav as $item)
                    <a href="{{ route($item['route']) }}" @class([
                        'inline-flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium',
                        'bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300' => request()->routeIs(...$item['match']),
                        'text-zinc-600 dark:text-zinc-300' => ! request()->routeIs(...$item['match']),
                    ])>
                        {{ $item['label'] }}
                        @if (! empty($item['count']))
                            <span class="rounded-full bg-primary-600 px-1.5 text-xs font-semibold text-white">{{ $item['count'] }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>
        @endauth
    </header>

    <main class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        {{ $slot }}
    </main>
</body>
</html>
