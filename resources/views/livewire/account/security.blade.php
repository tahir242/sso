@php
    $secondaryButton = 'inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium text-zinc-700 ring-1 ring-zinc-300 transition hover:bg-zinc-50 dark:text-zinc-200 dark:ring-zinc-700 dark:hover:bg-zinc-800';
    $dangerButton = 'inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium text-primary-700 ring-1 ring-primary-200 transition hover:bg-primary-50 dark:text-primary-300 dark:ring-primary-900 dark:hover:bg-primary-950';
@endphp

<div class="mx-auto max-w-3xl">
    <div>
        <p class="text-sm font-medium text-primary-700 dark:text-primary-400">My account</p>
        <h1 class="mt-1 text-3xl font-semibold tracking-tight">Security</h1>
        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Manage how you sign in to your applications.</p>
        <x-account-tabs class="mt-6" />
    </div>

    @if (session('status'))
        <p class="mt-6 rounded-xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-900" role="status">{{ session('status') }}</p>
    @endif

    <div class="mt-8 space-y-6">
        {{-- Two-step verification --}}
        <x-card title="Two-step verification" icon="device-phone-mobile"
                description="A code from your phone in addition to your password.">
            <x-slot:actions>
                @if ($user->hasTwoFactorEnabled())
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:ring-emerald-900">
                        <span class="size-1.5 rounded-full bg-emerald-500"></span> On
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                        <span class="size-1.5 rounded-full bg-zinc-400"></span> Off
                    </span>
                @endif
            </x-slot:actions>

            @if ($newRecoveryCodes)
                <x-mfa.recovery-codes :codes="$newRecoveryCodes" class="mb-6" />
            @endif

            @if ($settingUp)
                <x-mfa.qr-setup :qr-code="$qrCode" :secret="$secret" />
                <form wire:submit="confirmSetup" class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-start">
                    <x-text-input wire:model="code" name="code" label="6-digit code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required class="flex-1" />
                    <div class="flex gap-2 sm:mt-7">
                        <x-primary-button target="confirmSetup" loading="Checking…" class="!w-auto !py-2.5">Turn on</x-primary-button>
                        <button type="button" wire:click="cancelSetup" class="{{ $secondaryButton }}">Cancel</button>
                    </div>
                </form>
            @elseif ($user->hasTwoFactorEnabled())
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Turned on</dt>
                        <dd class="mt-1 font-medium">{{ $user->two_factor_confirmed_at->format('j M Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Recovery codes left</dt>
                        <dd @class(['mt-1 font-medium', 'text-primary-700 dark:text-primary-400' => $recoveryCodesLeft < 3])>{{ $recoveryCodesLeft }} of 8</dd>
                    </div>
                </dl>

                <div class="mt-6 rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200 dark:bg-zinc-950 dark:ring-zinc-800">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Enter a current code from your app to make changes.</p>
                    <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-start">
                        <x-text-input wire:model="code" name="code" label="Authentication code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" class="flex-1" />
                        <div class="flex flex-wrap gap-2 sm:mt-7">
                            <button type="button" wire:click="regenerateRecoveryCodes" class="{{ $secondaryButton }}">New recovery codes</button>
                            @if ($canDisableMfa)
                                <button type="button" wire:click="disableTwoFactor" wire:confirm="Turn off two-step verification? Your account will be less secure." class="{{ $dangerButton }}">Turn off</button>
                            @endif
                        </div>
                    </div>
                    @unless ($canDisableMfa)
                        <p class="mt-3 text-xs text-zinc-500">Two-step verification is required for administrator accounts.</p>
                    @endunless
                </div>
            @else
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    @if ($mfaRequired)
                        Your account requires two-step verification.
                    @else
                        Recommended. Protects your account even if someone learns your password.
                    @endif
                </p>
                <button type="button" wire:click="startSetup" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">
                    <x-heroicon-o-shield-check class="size-4" /> Set up
                </button>
            @endif
        </x-card>

        {{-- Remembered computers --}}
        @if ($user->hasTwoFactorEnabled())
            <x-card title="Remembered computers" icon="computer-desktop" description="These browsers skip the code step until the date shown.">
                @forelse ($devices as $device)
                    <div class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0 [&+&]:border-t [&+&]:border-zinc-100 dark:[&+&]:border-zinc-800">
                        <div class="min-w-0">
                            <p class="text-sm font-medium">{{ $device->label }}</p>
                            <p class="text-xs text-zinc-500">Last used {{ $device->last_used_at?->diffForHumans() ?? 'never' }} · {{ $device->ip_address }} · until {{ $device->expires_at->format('j M Y') }}</p>
                        </div>
                        <button type="button" wire:click="revokeDevice({{ $device->id }})" class="{{ $secondaryButton }}">Forget</button>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">No remembered computers.</p>
                @endforelse
            </x-card>
        @endif

        {{-- Sessions --}}
        <x-card title="Where you’re signed in" icon="globe-alt" description="Active single sign-on sessions.">
            @if (session('sessions-status'))
                <p class="mb-4 text-sm text-emerald-700 dark:text-emerald-400">{{ session('sessions-status') }}</p>
            @endif
            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($sessions as $session)
                    <li class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                        <div>
                            <p class="text-sm font-medium">{{ $session->device }}
                                @if ($session->current)
                                    <span class="ml-2 rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-950 dark:text-primary-300">This browser</span>
                                @endif
                            </p>
                            <p class="text-xs text-zinc-500">{{ $session->ip }} · active {{ $session->last_active->diffForHumans() }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($sessions->count() > 1)
                <button type="button" wire:click="signOutOtherSessions" wire:confirm="Sign out of all other browsers?" class="mt-4 {{ $secondaryButton }}">Sign out other sessions</button>
            @endif
        </x-card>

        {{-- Password --}}
        <x-card title="Password" icon="key">
            @if ($isLocalAccount)
                @if (session('password-status'))
                    <p class="mb-4 text-sm text-emerald-700 dark:text-emerald-400">{{ session('password-status') }}</p>
                @endif
                <form wire:submit="changePassword" class="grid gap-4 sm:max-w-sm">
                    <x-text-input wire:model="current_password" name="current_password" type="password" label="Current password" autocomplete="current-password" required />
                    <x-text-input wire:model="password" name="password" type="password" label="New password" autocomplete="new-password" required
                                  :hint="'At least '.app(\App\Domain\Settings\SecuritySettings::class)->password_min_length.' characters with letters and numbers.'" />
                    <x-text-input wire:model="password_confirmation" name="password_confirmation" type="password" label="Confirm new password" autocomplete="new-password" required />
                    <x-primary-button target="changePassword" loading="Saving…" class="sm:!w-auto">Change password</x-primary-button>
                </form>
            @else
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Your password is your Windows (network) password. Change it on a hospital computer with <kbd class="rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-xs dark:bg-zinc-800">Ctrl</kbd> + <kbd class="rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-xs dark:bg-zinc-800">Alt</kbd> + <kbd class="rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-xs dark:bg-zinc-800">Del</kbd> → Change a password.</p>
            @endif
        </x-card>

        {{-- Activity --}}
        <x-card title="Recent activity" icon="clock">
            <ul class="divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                @forelse ($events as $event)
                    <li class="flex items-center justify-between gap-4 py-2.5 first:pt-0 last:pb-0">
                        <span @class([
                            'font-medium',
                            'text-primary-700 dark:text-primary-400' => $event->event->getColor() === 'danger',
                        ])>{{ $event->event->getLabel() }}</span>
                        <span class="text-right text-xs text-zinc-500">{{ $event->ip_address }} · {{ $event->created_at->format('j M, H:i') }}</span>
                    </li>
                @empty
                    <li class="text-zinc-500">No activity yet.</li>
                @endforelse
            </ul>
        </x-card>
    </div>
</div>
