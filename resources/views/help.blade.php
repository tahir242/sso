<x-layouts::auth title="Help & FAQ">
    @php
        $back = auth()->check()
            ? ['url' => route('home'), 'label' => 'Back to my applications']
            : ['url' => route('login'), 'label' => 'Back to sign in'];
    @endphp

    <div class="min-h-full bg-zinc-50 dark:bg-zinc-950"
         x-data="{
             q: '',
             matches(el) { return this.q.trim() === '' || el.dataset.search.includes(this.q.trim().toLowerCase()); },
             visibleIn(section) { return [...section.querySelectorAll('[data-search]')].some(el => this.matches(el)); },
             none() { return [...this.$root.querySelectorAll('[data-search]')].every(el => ! this.matches(el)); },
         }"
         x-init="if (location.hash) { const d = document.querySelector(location.hash); if (d && d.tagName === 'DETAILS') { d.open = true; $nextTick(() => d.scrollIntoView({ block: 'start' })); } }">

        <header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <a href="{{ $back['url'] }}"><x-brand-mark /></a>
                <div class="flex items-center gap-1">
                    <x-theme-toggle />
                    <a href="{{ $back['url'] }}" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                        <x-heroicon-m-arrow-left class="size-4" />
                        <span class="hidden sm:inline">{{ $back['label'] }}</span>
                        <span class="sm:hidden">Back</span>
                    </a>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
            <h1 class="text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">Help &amp; frequently asked questions</h1>
            <p class="mt-2 text-zinc-600 dark:text-zinc-400">Signing in, passwords, two-step verification and getting access to applications.</p>

            <label for="faq-search" class="sr-only">Search the questions</label>
            <div class="relative mt-6">
                <x-heroicon-o-magnifying-glass class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-zinc-400" />
                <input id="faq-search" type="search" x-model="q" placeholder="Search, e.g. “forgot password” or “locked”"
                       class="w-full rounded-xl border-0 bg-white py-3 pr-4 pl-11 text-sm text-zinc-900 shadow-sm ring-1 ring-zinc-300 placeholder:text-zinc-400 focus:ring-2 focus:ring-primary-600 focus:outline-none dark:bg-zinc-900 dark:text-white dark:ring-zinc-700">
            </div>

            <nav class="mt-4 flex flex-wrap gap-2" aria-label="Topics" x-show="q.trim() === ''">
                @foreach ($sections as $section)
                    <a href="#{{ $section['id'] }}" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 ring-1 ring-zinc-200 transition hover:bg-zinc-100 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-800 dark:hover:bg-zinc-800">
                        <x-dynamic-component :component="$section['icon']" class="size-4 text-primary-600 dark:text-primary-400" />
                        {{ $section['title'] }}
                    </a>
                @endforeach
            </nav>

            @foreach ($sections as $section)
                <section id="{{ $section['id'] }}" class="mt-10 scroll-mt-6" x-show="visibleIn($el)">
                    <h2 class="flex items-center gap-2 text-lg font-semibold text-zinc-900 dark:text-white">
                        <x-dynamic-component :component="$section['icon']" class="size-5 text-primary-600 dark:text-primary-400" />
                        {{ $section['title'] }}
                    </h2>

                    <div class="mt-3 divide-y divide-zinc-200 overflow-hidden rounded-2xl bg-white ring-1 ring-zinc-200 dark:divide-zinc-800 dark:bg-zinc-900 dark:ring-zinc-800">
                        @foreach ($section['items'] as $item)
                            <details id="{{ $item['id'] }}" class="group" data-search="{{ mb_strtolower($item['q'].' '.strip_tags($item['a'])) }}" x-show="matches($el)">
                                <summary class="flex cursor-pointer list-none items-start justify-between gap-4 px-5 py-4 text-left font-medium text-zinc-900 transition hover:bg-zinc-50 dark:text-white dark:hover:bg-zinc-800/60 [&::-webkit-details-marker]:hidden">
                                    <span>{{ $item['q'] }}</span>
                                    <x-heroicon-m-chevron-down class="mt-0.5 size-5 shrink-0 text-zinc-400 transition group-open:rotate-180" />
                                </summary>
                                <div class="prose prose-sm prose-zinc max-w-none px-5 pb-5 dark:prose-invert prose-a:text-primary-700 dark:prose-a:text-primary-400 prose-kbd:rounded prose-kbd:bg-zinc-100 prose-kbd:px-1.5 dark:prose-kbd:bg-zinc-800">
                                    {!! $item['a'] !!}
                                </div>
                            </details>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <p x-show="none()" x-cloak class="mt-10 rounded-2xl bg-white p-6 text-center text-sm text-zinc-600 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-400 dark:ring-zinc-800">
                No question matches “<span x-text="q"></span>”. Try another word, or contact the IT helpdesk below.
            </p>

            <div class="mt-12 flex items-start gap-4 rounded-2xl bg-primary-50 p-6 ring-1 ring-primary-100 dark:bg-primary-950/40 dark:ring-primary-900">
                <x-heroicon-o-lifebuoy class="size-6 shrink-0 text-primary-700 dark:text-primary-400" />
                <div class="text-sm text-zinc-700 dark:text-zinc-300">
                    <p class="font-semibold text-zinc-900 dark:text-white">Still need help?</p>
                    <p class="mt-1 [&_a]:font-medium [&_a]:text-primary-700 dark:[&_a]:text-primary-400">
                        Contact the IT helpdesk{!! $helpdesk !!}.
                        @auth
                            You can also <a href="{{ route('support.new') }}">open an IT help ticket</a>.
                        @endauth
                    </p>
                </div>
            </div>

            <p class="mt-10 text-center text-xs text-zinc-500">&copy; {{ now()->year }} {{ $settings->organization_name }}</p>
        </main>
    </div>

    {{-- A plain page (no Livewire component): load Livewire's bundle for Alpine (search, theme toggle). --}}
    @livewireScripts
</x-layouts::auth>
