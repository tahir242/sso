<x-layouts::auth title="No access">
    <x-auth-shell :settings="app(\App\Domain\Settings\GeneralSettings::class)">
        <div class="grid size-12 place-items-center rounded-2xl bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
            <x-heroicon-o-no-symbol class="size-6" />
        </div>
        <h1 class="mt-6 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">You don’t have access to {{ $application->name }}</h1>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
            You are signed in as <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ auth()->user()->name }}</span>,
            but this account has not been given access to {{ $application->name }}.
            You can request access; your head of department and the application owner will be asked to approve it.
        </p>

        <div class="mt-8 space-y-3">
            <a href="{{ route('access.request', ['app' => $application->slug]) }}" class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">
                Request access
            </a>
            <a href="{{ route('home') }}" class="flex w-full justify-center rounded-xl px-4 py-3 text-sm font-medium text-zinc-700 ring-1 ring-zinc-300 transition hover:bg-zinc-50 dark:text-zinc-200 dark:ring-zinc-700 dark:hover:bg-zinc-800">
                Go to my applications
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full justify-center rounded-xl px-4 py-3 text-sm font-medium text-zinc-700 ring-1 ring-zinc-300 transition hover:bg-zinc-50 dark:text-zinc-200 dark:ring-zinc-700 dark:hover:bg-zinc-800">
                    Sign in as someone else
                </button>
            </form>
        </div>
    </x-auth-shell>
</x-layouts::auth>
