<x-layouts::auth title="Sign out">
    <x-auth-shell :settings="app(\App\Domain\Settings\GeneralSettings::class)">
        <div class="grid size-12 place-items-center rounded-2xl bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300">
            <x-heroicon-o-arrow-right-start-on-rectangle class="size-6" />
        </div>
        <h1 class="mt-6 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">Sign out everywhere?</h1>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
            @if ($application)
                {{ $application->name }} asked to sign you out.
            @endif
            You’ll be signed out of all applications on this browser, {{ $user->name }}.
        </p>

        <form method="POST" action="{{ route('oidc.logout.confirm') }}" class="mt-8 space-y-3">
            @csrf
            <x-primary-button>Sign out</x-primary-button>
            <a href="{{ route('home') }}" class="flex w-full justify-center rounded-xl px-4 py-3 text-sm font-medium text-zinc-700 ring-1 ring-zinc-300 transition hover:bg-zinc-50 dark:text-zinc-200 dark:ring-zinc-700 dark:hover:bg-zinc-800">Stay signed in</a>
        </form>
    </x-auth-shell>
</x-layouts::auth>
