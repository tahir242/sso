<x-layouts::auth title="Sign-in problem">
    <x-auth-shell :settings="app(\App\Domain\Settings\GeneralSettings::class)">
        <div class="grid size-12 place-items-center rounded-2xl bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300">
            <x-heroicon-o-exclamation-triangle class="size-6" />
        </div>
        <h1 class="mt-6 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">Something went wrong</h1>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $message }}</p>
        <a href="{{ route('home') }}" class="mt-8 flex w-full justify-center rounded-xl bg-primary-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">Go to my applications</a>
    </x-auth-shell>
</x-layouts::auth>
