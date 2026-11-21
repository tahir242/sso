<div class="mx-auto max-w-3xl">
    <p class="text-sm font-medium text-primary-700 dark:text-primary-400">My account</p>
    <h1 class="mt-1 text-3xl font-semibold tracking-tight">Notifications</h1>
    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Choose how you hear about approvals and security events. Everything also appears under the bell.</p>
    <x-account-tabs class="mt-6" />

    @if (session('status'))
        <p class="mt-6 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-900" role="status">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="mt-6 rounded-xl bg-primary-50 p-4 text-sm text-primary-800 ring-1 ring-primary-200 dark:bg-primary-950/40 dark:text-primary-200 dark:ring-primary-900" role="alert">{{ session('error') }}</p>
    @endif

    <div class="mt-8 space-y-6">
        {{-- Where we can reach you --}}
        <x-card title="Where we can reach you" icon="signal">
            <ul class="space-y-3 text-sm">
                <li class="flex items-center justify-between gap-4">
                    <span><span class="font-medium">Email</span> <span class="text-zinc-500">· {{ $user->email ?: 'no email address on record' }}</span></span>
                    <x-channel-status :ok="in_array(\App\Domain\Notifications\Channel::Mail, $available, true)" />
                </li>
                <li class="flex items-center justify-between gap-4">
                    <span><span class="font-medium">WhatsApp</span> <span class="text-zinc-500">· {{ $whatsappReady ? ($user->mobile ?: 'no mobile number on record') : 'not enabled yet' }}</span></span>
                    <x-channel-status :ok="in_array(\App\Domain\Notifications\Channel::WhatsApp, $available, true)" />
                </li>
                <li class="flex flex-wrap items-center justify-between gap-4"
                    x-data="pushSubscription(@js($vapidPublicKey), @js(route('account.push.store')), @js(route('account.push.destroy')))">
                    <span>
                        <span class="font-medium">Browser</span>
                        <span class="text-zinc-500">· @if (! $webpushReady) not enabled yet @else {{ $pushDevices }} {{ Str::plural('device', $pushDevices) }} @endif</span>
                        <span class="block text-xs text-zinc-500" x-show="message" x-text="message" x-cloak></span>
                    </span>
                    @if ($webpushReady)
                        <span class="flex items-center gap-2">
                            <button type="button" x-show="supported && !subscribed" x-on:click="subscribe()" x-cloak
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-primary-700 ring-1 ring-primary-200 transition hover:bg-primary-50 dark:text-primary-300 dark:ring-primary-900 dark:hover:bg-primary-950">Turn on for this browser</button>
                            <button type="button" x-show="subscribed" x-on:click="unsubscribe()" x-cloak
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-zinc-700 ring-1 ring-zinc-300 transition hover:bg-zinc-50 dark:text-zinc-200 dark:ring-zinc-700 dark:hover:bg-zinc-800">Turn off for this browser</button>
                        </span>
                    @else
                        <x-channel-status :ok="false" />
                    @endif
                </li>
            </ul>
            <p class="mt-4 text-xs text-zinc-500">Email and mobile number come from your HR / Windows account.</p>
        </x-card>

        {{-- Preferences --}}
        <form wire:submit="save">
            <x-card title="What to send where" icon="bell">
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($categories as $category)
                        <fieldset class="py-4 first:pt-0 last:pb-0" wire:key="cat-{{ $category->value }}">
                            <legend class="sr-only">{{ $category->label() }}</legend>
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-sm font-medium">{{ $category->label() }}</p>
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $category->description() }}</p>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    @foreach ($channels as $channel)
                                        @php($ready = in_array($channel, $available, true))
                                        <label @class([
                                            'flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm ring-1 transition has-[:checked]:bg-primary-50 has-[:checked]:ring-primary-300 dark:has-[:checked]:bg-primary-950/50 dark:has-[:checked]:ring-primary-800',
                                            'ring-zinc-200 dark:ring-zinc-700 cursor-pointer' => $ready,
                                            'ring-zinc-100 text-zinc-400 dark:ring-zinc-800 cursor-not-allowed' => ! $ready,
                                        ]) @unless ($ready) title="Not available for you yet" @endunless>
                                            <input type="checkbox" value="{{ $channel->value }}" wire:model="preferences.{{ $category->value }}" @disabled(! $ready) class="size-4 accent-primary-600">
                                            {{ $channel->label() }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            @error("preferences.{$category->value}") <p class="mt-2 text-sm text-primary-700 dark:text-primary-400" role="alert">{{ $message }}</p> @enderror
                        </fieldset>
                    @endforeach
                </div>
                <div class="mt-6 flex flex-wrap justify-end gap-2">
                    <button type="button" wire:click="sendTest" wire:loading.attr="disabled"
                            class="rounded-xl px-4 py-2.5 text-sm font-medium text-zinc-700 ring-1 ring-zinc-300 transition hover:bg-zinc-50 dark:text-zinc-200 dark:ring-zinc-700 dark:hover:bg-zinc-800">Send me a test</button>
                    <x-primary-button target="save" loading="Saving…" class="!w-auto !py-2.5">Save</x-primary-button>
                </div>
            </x-card>
        </form>
    </div>
</div>
