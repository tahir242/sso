<x-auth-shell :greeting="$greeting" :settings="$settings">
    <div class="grid size-12 place-items-center rounded-2xl bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300">
        <x-heroicon-o-device-phone-mobile class="size-6" />
    </div>

    <h1 class="mt-6 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">Verify it’s you</h1>
    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
        @if ($usingRecoveryCode)
            Enter one of the recovery codes you saved when you set up two-step verification.
        @else
            Open your authenticator app and enter the 6-digit code it shows for this account.
        @endif
    </p>

    <form wire:submit="verify" class="mt-8 space-y-5" novalidate>
        @if ($usingRecoveryCode)
            <x-text-input wire:model="code" name="code" label="Recovery code" icon="key" autocomplete="off" autocapitalize="none" spellcheck="false" autofocus required placeholder="xxxxx-xxxxx" />
        @else
            <x-text-input
                wire:model="code"
                name="code"
                label="Authentication code"
                icon="shield-check"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="7"
                autofocus
                required
                placeholder="123 456"
                class="[&_input]:text-lg [&_input]:tracking-[0.3em]"
            />
        @endif

        @if ($canTrustDevice)
            <label class="flex items-start gap-3 text-sm text-zinc-700 dark:text-zinc-300">
                <input type="checkbox" wire:model="trustDevice" class="mt-0.5 size-4 rounded border-zinc-300 text-primary-600 accent-primary-600 focus:ring-primary-600">
                <span>Don’t ask again on this computer for {{ $trustDays }} days.<span class="block text-xs text-zinc-500">Don’t tick this on shared ward computers.</span></span>
            </label>
        @endif

        <x-primary-button target="verify" loading="Verifying…">Verify</x-primary-button>
    </form>

    <div class="mt-6 flex items-center justify-between text-sm">
        <button type="button" wire:click="toggleRecovery" class="font-medium text-primary-700 underline-offset-2 hover:underline dark:text-primary-400">
            {{ $usingRecoveryCode ? 'Use authenticator app instead' : 'Use a recovery code' }}
        </button>
        <a href="{{ route('login') }}" class="text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">Cancel</a>
    </div>
</x-auth-shell>
