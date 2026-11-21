@php
    $tones = [
        'rose' => 'bg-rose-50 text-rose-600 ring-rose-100 dark:bg-rose-950/50 dark:text-rose-300 dark:ring-rose-900',
        'primary' => 'bg-primary-50 text-primary-600 ring-primary-100 dark:bg-primary-950/50 dark:text-primary-300 dark:ring-primary-900',
        'amber' => 'bg-amber-50 text-amber-600 ring-amber-100 dark:bg-amber-950/50 dark:text-amber-300 dark:ring-amber-900',
        'emerald' => 'bg-emerald-50 text-emerald-600 ring-emerald-100 dark:bg-emerald-950/50 dark:text-emerald-300 dark:ring-emerald-900',
    ];
@endphp

<div class="mx-auto max-w-3xl">
    <x-breadcrumbs :items="[['My requests', route('access.requests')], ['New request', null]]" />
    <h1 class="mt-6 text-3xl font-semibold tracking-tight">What do you need?</h1>
    <p class="mt-2 text-zinc-600 dark:text-zinc-400">Choose the closest match. You can follow everything you send under <a href="{{ route('access.requests') }}" class="font-medium text-primary-700 underline-offset-2 hover:underline dark:text-primary-400">My requests</a>.</p>

    <ul class="mt-8 grid gap-4 sm:grid-cols-2">
        @foreach ($choices as $choice)
            <li>
                <a href="{{ $choice['url'] }}" class="group flex h-full flex-col rounded-2xl bg-white p-5 shadow-sm ring-1 ring-zinc-200 transition hover:-translate-y-0.5 hover:shadow-lg hover:ring-primary-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:ring-primary-800">
                    <span class="grid size-11 place-items-center rounded-xl ring-1 {{ $tones[$choice['tone']] }}">
                        <x-dynamic-component :component="$choice['icon']" class="size-6" />
                    </span>
                    <span class="mt-4 flex items-center gap-1.5 font-semibold text-zinc-900 dark:text-white">
                        {{ $choice['title'] }}
                        <x-heroicon-m-arrow-right class="size-4 text-zinc-400 transition group-hover:translate-x-0.5 group-hover:text-primary-600" />
                    </span>
                    <span class="mt-1.5 text-sm text-zinc-600 dark:text-zinc-400">{{ $choice['text'] }}</span>
                    <span class="mt-auto pt-4 text-xs text-zinc-500 dark:text-zinc-500">{{ $choice['who'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <p class="mt-6 flex items-start gap-2 rounded-xl bg-zinc-100 p-4 text-sm text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
        <x-heroicon-o-information-circle class="mt-0.5 size-5 shrink-0 text-zinc-500" />
        <span>Not sure which one? Choose <a href="{{ route('support.new') }}" class="font-medium text-primary-700 underline-offset-2 hover:underline dark:text-primary-400">Something isn’t working</a> — the helpdesk will pass it to the right team.</span>
    </p>
</div>
