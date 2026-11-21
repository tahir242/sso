@props(['default' => 'system'])

{{-- Cycles light → dark → system. Preference is a per-browser convenience only. --}}
<button
    type="button"
    x-data="{
        theme: (() => { try { return localStorage.getItem('theme') || @js($default) } catch (e) { return @js($default) } })(),
        cycle() {
            this.theme = { light: 'dark', dark: 'system', system: 'light' }[this.theme];
            try { localStorage.setItem('theme', this.theme) } catch (e) {}
            const dark = this.theme === 'dark' || (this.theme === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        },
    }"
    x-on:click="cycle()"
    x-bind:title="'Theme: ' + theme"
    x-bind:aria-label="'Change theme (current: ' + theme + ')'"
    {{ $attributes->merge(['class' => 'inline-flex size-9 items-center justify-center rounded-full text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white']) }}
>
    <x-heroicon-o-sun class="size-5" x-show="theme === 'light'" />
    <x-heroicon-o-moon class="size-5" x-show="theme === 'dark'" x-cloak />
    <x-heroicon-o-computer-desktop class="size-5" x-show="theme === 'system'" x-cloak />
</button>
