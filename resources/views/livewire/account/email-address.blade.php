<x-auth-shell :settings="$settings">
    <div class="animate-[fade-up_.35s_ease-out]">
        <span class="grid size-12 place-items-center rounded-2xl bg-primary-50 text-primary-700 ring-1 ring-primary-100 dark:bg-primary-950 dark:text-primary-300 dark:ring-primary-900">
            <x-heroicon-o-envelope class="size-6" />
        </span>
        <h1 class="mt-5 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">Add your email address</h1>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
            Hi {{ str($user->name)->before(' ') }} — your account needs an email address so we can help you recover access and warn you about unusual sign-ins. You only have to do this once.
        </p>
    </div>

    <form wire:submit="save" class="mt-8 space-y-5">
        <x-text-input wire:model="email" name="email" type="email" label="Work email address" icon="envelope"
                      autocomplete="email" autofocus required maxlength="191" placeholder="name@example.com" />

        <x-primary-button target="save" loading="Saving…">
            Save and continue <x-heroicon-o-arrow-right class="size-4 transition group-hover:translate-x-0.5" />
        </x-primary-button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-6 text-center">
        @csrf
        <button type="submit" class="text-sm text-zinc-500 underline-offset-2 hover:text-zinc-800 hover:underline dark:hover:text-zinc-200">Sign out instead</button>
    </form>
</x-auth-shell>
