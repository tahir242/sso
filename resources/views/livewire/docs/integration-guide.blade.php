@php
    $navLink = 'shrink-0 rounded-lg px-3 py-2 text-sm font-medium transition';
    $navOn = 'bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300';
    $navOff = 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800';
@endphp

<div class="lg:grid lg:grid-cols-[14rem_1fr] lg:gap-10">
    <aside class="mb-8 lg:mb-0">
        @if ($application)
            <p class="mb-4 rounded-lg bg-primary-50 px-3 py-2 text-sm text-primary-800 ring-1 ring-primary-100 dark:bg-primary-950/40 dark:text-primary-200 dark:ring-primary-900">
                Values filled in for <span class="font-semibold">{{ $application->name }}</span>
            </p>
        @endif

        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400">Connect your application</p>
        <nav class="mt-2 flex gap-1 overflow-x-auto lg:flex-col" aria-label="Application guides">
            @foreach ($pages as $key => $label)
                <a href="{{ route('docs.integration', ['page' => $key, 'app' => $app ?: null]) }}" wire:navigate @class([$navLink, $navOn => $page === $key, $navOff => $page !== $key]) @if ($page === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
            <a href="{{ url('/docs/api') }}" @class([$navLink, $navOff])>API reference ↗</a>
        </nav>

        <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-zinc-400">Third-party software</p>
        <nav class="mt-2 flex gap-1 overflow-x-auto lg:flex-col" aria-label="Third-party software guides">
            @foreach ($softwarePages as $key => $label)
                <a href="{{ route('docs.integration', ['page' => $key, 'app' => $app ?: null]) }}" wire:navigate @class([$navLink, $navOn => $page === $key, $navOff => $page !== $key]) @if ($page === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    </aside>

    <div class="min-w-0 max-w-3xl">
        @if ($software)
            <x-docs.architecture :product="$software[0]" :protocol="$software[1]" class="mb-8" />
        @endif

        <article class="prose prose-zinc min-w-0 max-w-none dark:prose-invert prose-headings:tracking-tight prose-a:text-primary-700 dark:prose-a:text-primary-400 prose-code:before:content-none prose-code:after:content-none prose-code:rounded prose-code:bg-zinc-100 prose-code:px-1 prose-code:py-0.5 prose-code:font-normal dark:prose-code:bg-zinc-800 prose-pre:bg-zinc-900 prose-pre:text-zinc-100 [&_pre_code]:bg-transparent [&_pre_code]:p-0 prose-table:text-sm">
            {!! $html !!}
        </article>
    </div>
</div>
