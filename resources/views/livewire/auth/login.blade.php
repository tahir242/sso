<x-auth-shell :greeting="$greeting" :settings="$settings">
    {{-- Compact greeting on small screens --}}
    @if ($greeting)
        <div class="mb-8 rounded-2xl bg-gradient-to-br from-primary-600 to-primary-900 p-1 lg:hidden">
            <x-login-greeting :greeting="$greeting" class="!bg-none !p-5 !ring-0" />
        </div>
    @endif

    <div
        x-data
        x-on:focus-password.window="$nextTick(() => document.getElementById('password')?.focus())"
        x-on:focus-identifier.window="$nextTick(() => document.getElementById('identifier')?.focus())"
    >
        @if (session('status'))
            <p class="mb-6 rounded-xl bg-amber-50 p-3 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-900" role="status">{{ session('status') }}</p>
        @endif

        @if ($account)
            {{-- Returning user: Windows-style profile + password only --}}
            <div wire:key="account-{{ $account['username'] }}" class="animate-[fade-up_.35s_ease-out] text-center">
                <x-avatar :initials="$account['initials']" :color="$account['color']" size="size-24 text-3xl" class="mx-auto ring-4 shadow-lg" />
                <h1 class="mt-5 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $account['name'] }}</h1>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $account['username'] }}</p>
            </div>

            {{-- Empty fields are caught here, in the browser: nothing is sent to the server. --}}
            <form x-data="{ missing: '', submit() { const id = this.$root.querySelector('#identifier'); const pw = this.$root.querySelector('#password'); if (id && id.value.trim() === '') { this.missing = 'Enter your username or Employee ID.'; id.focus(); return; } if (pw.value === '') { this.missing = 'Enter your password.'; pw.focus(); return; } this.missing = ''; this.$wire.authenticate(); } }" x-on:submit.prevent="submit()" x-on:input="missing = ''" class="mt-8 space-y-4" novalidate>
                @error('identifier')
                    <p class="rounded-xl bg-primary-50 p-3 text-sm text-primary-800 ring-1 ring-primary-200 dark:bg-primary-950/40 dark:text-primary-200 dark:ring-primary-900" role="alert">{{ $message }}</p>
                @enderror

                <input type="hidden" name="username" value="{{ $account['username'] }}" autocomplete="username">

                <div x-data="{ show: false }" class="relative">
                    <label for="password" class="sr-only">Password for {{ $account['name'] }}</label>
                    <x-heroicon-o-lock-closed class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-zinc-400" />
                    <input id="password" wire:model="password" x-bind:type="show ? 'text' : 'password'" type="password"
                           autocomplete="current-password" required autofocus placeholder="Password"
                           @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                           class="block w-full rounded-xl border-0 bg-white py-3 pl-10 pr-11 text-zinc-900 shadow-sm ring-1 ring-zinc-300 transition placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-primary-600 dark:bg-zinc-900 dark:text-white dark:ring-zinc-700 dark:focus:ring-primary-400 sm:text-sm">
                    <button type="button" x-on:click="show = !show" class="absolute right-2 top-1/2 grid size-8 -translate-y-1/2 place-items-center rounded-lg text-zinc-400 transition hover:text-zinc-700 dark:hover:text-zinc-200" x-bind:aria-label="show ? 'Hide password' : 'Show password'">
                        <x-heroicon-o-eye class="size-5" x-show="!show" />
                        <x-heroicon-o-eye-slash class="size-5" x-show="show" x-cloak />
                    </button>
                </div>
                @error('password')
                    <p id="password-error" class="text-sm text-primary-700 dark:text-primary-400" role="alert">{{ $message }}</p>
                @enderror

                <p x-show="missing" x-text="missing" x-cloak class="flex items-center gap-1.5 text-sm font-medium text-primary-700 dark:text-primary-400" role="alert"></p>

                <x-primary-button target="authenticate" loading="Signing in…">
                    Sign in <x-heroicon-o-arrow-right class="size-4 transition group-hover:translate-x-0.5" />
                </x-primary-button>

                <p class="text-center text-xs">
                    <button type="button" wire:click="forgetAccount(@js($account['username']))" class="text-zinc-500 underline-offset-2 hover:text-zinc-800 hover:underline dark:hover:text-zinc-200">
                        Remove this account from this computer
                    </button>
                </p>
            </form>
        @else
            {{-- New or other user: full form --}}
            <div wire:key="other-user" class="animate-[fade-up_.35s_ease-out]">
                <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $recent ? 'Other user' : 'Sign in' }}</h1>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">Use your network (Windows) account or local credentials.</p>
            </div>

            {{-- Empty fields are caught here, in the browser: nothing is sent to the server. --}}
            <form x-data="{ missing: '', submit() { const id = this.$root.querySelector('#identifier'); const pw = this.$root.querySelector('#password'); if (id && id.value.trim() === '') { this.missing = 'Enter your username or Employee ID.'; id.focus(); return; } if (pw.value === '') { this.missing = 'Enter your password.'; pw.focus(); return; } this.missing = ''; this.$wire.authenticate(); } }" x-on:submit.prevent="submit()" x-on:input="missing = ''" class="mt-8 space-y-5" novalidate>
                <x-text-input
                    wire:model="identifier"
                    name="identifier"
                    label="Username or Employee ID"
                    icon="user"
                    autocomplete="username"
                    autocapitalize="none"
                    spellcheck="false"
                    autofocus
                    required
                    placeholder="e.g. ahmed.khan or 10234"
                />

                <x-text-input
                    x-data="{ show: false }"
                    wire:model="password"
                    name="password"
                    label="Password"
                    icon="lock-closed"
                    type="password"
                    x-bind:type="show ? 'text' : 'password'"
                    autocomplete="current-password"
                    required
                    class="[&_input]:pr-11"
                >
                    <button type="button" x-on:click="show = !show" class="absolute right-2 top-1/2 grid size-8 -translate-y-1/2 place-items-center rounded-lg text-zinc-400 transition hover:text-zinc-700 dark:hover:text-zinc-200" x-bind:aria-label="show ? 'Hide password' : 'Show password'">
                        <x-heroicon-o-eye class="size-5" x-show="!show" />
                        <x-heroicon-o-eye-slash class="size-5" x-show="show" x-cloak />
                    </button>
                </x-text-input>

                <p x-show="missing" x-text="missing" x-cloak class="flex items-center gap-1.5 text-sm font-medium text-primary-700 dark:text-primary-400" role="alert"></p>

                <x-primary-button target="authenticate" loading="Signing in…">
                    Sign in <x-heroicon-o-arrow-right class="size-4 transition group-hover:translate-x-0.5" />
                </x-primary-button>
            </form>
        @endif

        {{-- Profiles remembered on this computer (Windows-style user switcher) --}}
        @if ($recent)
            <nav class="mt-10" aria-label="Accounts on this computer">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Accounts on this computer</p>
                <ul class="mt-3 space-y-1.5">
                    @foreach ($recent as $item)
                        @continue($item['username'] === $selected)
                        <li wire:key="recent-{{ $item['username'] }}" class="group relative">
                            <button type="button" wire:click="selectAccount(@js($item['username']))"
                                    class="flex w-full items-center gap-3 rounded-xl p-2 pr-10 text-left transition hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-primary-600 dark:hover:bg-zinc-800/70">
                                <x-avatar :initials="$item['initials']" :color="$item['color']" />
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $item['name'] }}</span>
                                    <span class="block truncate text-xs text-zinc-500">{{ $item['username'] }}</span>
                                </span>
                            </button>
                            <button type="button" wire:click="forgetAccount(@js($item['username']))"
                                    class="absolute right-2 top-1/2 grid size-7 -translate-y-1/2 place-items-center rounded-lg text-zinc-400 opacity-0 transition hover:bg-zinc-200 hover:text-zinc-700 focus:opacity-100 group-hover:opacity-100 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                                    aria-label="Remove {{ $item['name'] }} from this computer" title="Remove from this computer">
                                <x-heroicon-o-x-mark class="size-4" />
                            </button>
                        </li>
                    @endforeach
                    @if ($selected !== null)
                        <li>
                            <button type="button" wire:click="otherUser"
                                    class="flex w-full items-center gap-3 rounded-xl p-2 text-left transition hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-primary-600 dark:hover:bg-zinc-800/70">
                                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-zinc-200 text-zinc-600 ring-2 ring-white dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-900" aria-hidden="true">
                                    <x-heroicon-o-user-plus class="size-5" />
                                </span>
                                <span class="text-sm font-medium text-zinc-900 dark:text-white">Other user</span>
                            </button>
                        </li>
                    @endif
                </ul>
            </nav>
        @endif

        <div class="mt-8 flex items-start gap-3 rounded-xl bg-zinc-50 p-4 text-sm text-zinc-600 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-400 dark:ring-zinc-800">
            <x-heroicon-o-shield-check class="mt-0.5 size-5 shrink-0 text-primary-600 dark:text-primary-400" />
            <p>
                @php
                    $contacts = array_filter([
                        $settings->support_phone ? '<span class="font-medium text-zinc-900 dark:text-zinc-200">'.e($settings->support_phone).'</span>' : null,
                        $settings->support_email ? '<a href="mailto:'.e($settings->support_email).'" class="font-medium text-primary-700 underline-offset-2 hover:underline dark:text-primary-400">'.e($settings->support_email).'</a>' : null,
                    ]);
                @endphp
                Forgot your password? Contact the IT helpdesk{!! $contacts ? ' at '.implode(' or ', $contacts) : '' !!}.
                {{ $recent ? 'Never share your password, and remove your account from shared computers.' : 'Never share your password.' }}
            </p>
        </div>

        <p class="mt-4 text-center text-sm">
            <a href="{{ route('help') }}" class="inline-flex items-center gap-1.5 font-medium text-primary-700 underline-offset-2 hover:underline dark:text-primary-400">
                <x-heroicon-o-question-mark-circle class="size-4" />
                Help &amp; FAQ: trouble signing in, passwords, access
            </a>
        </p>
    </div>
</x-auth-shell>
