<x-auth-shell :greeting="$greeting" :settings="$settings">
    @if ($recoveryCodes)
        <div class="grid size-12 place-items-center rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
            <x-heroicon-o-check-badge class="size-6" />
        </div>
        <h1 class="mt-6 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">Two-step verification is on</h1>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">From now on you’ll be asked for a code from your phone when you sign in.</p>

        <x-mfa.recovery-codes :codes="$recoveryCodes" class="mt-6" />

        <x-primary-button type="button" wire:click="finish" target="finish" loading="Signing in…" class="mt-6">
            I’ve saved my codes — continue <x-heroicon-o-arrow-right class="size-4" />
        </x-primary-button>
    @else
        <div class="grid size-12 place-items-center rounded-2xl bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300">
            <x-heroicon-o-shield-check class="size-6" />
        </div>
        <h1 class="mt-6 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">Set up two-step verification</h1>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">Your account needs a second step to sign in. This takes about a minute.</p>

        @if ($qrCode)
            <x-mfa.qr-setup :qr-code="$qrCode" :secret="$secret" class="mt-6" />
        @endif

        <form wire:submit="confirm" class="mt-6 space-y-5" novalidate>
            <x-text-input wire:model="code" name="code" label="6-digit code" icon="device-phone-mobile" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required placeholder="123 456" class="[&_input]:text-lg [&_input]:tracking-[0.3em]" />
            <x-primary-button target="confirm" loading="Checking…">Turn on</x-primary-button>
        </form>

        <div class="mt-6 text-center text-sm">
            <a href="{{ route('login') }}" class="text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">Cancel and sign out</a>
        </div>
    @endif
</x-auth-shell>
