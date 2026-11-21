{{-- Sub-navigation for the My account pages. --}}
<nav {{ $attributes->class('flex gap-1 border-b border-zinc-200 dark:border-zinc-800') }} aria-label="My account">
    @foreach (['account.security' => 'Security', 'account.notifications' => 'Notifications'] as $route => $label)
        <a href="{{ route($route) }}" @class([
            '-mb-px border-b-2 px-3 py-2 text-sm font-medium transition',
            'border-primary-600 text-primary-700 dark:text-primary-400' => request()->routeIs($route),
            'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => ! request()->routeIs($route),
        ]) @if (request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
