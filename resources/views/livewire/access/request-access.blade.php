<div class="mx-auto max-w-2xl">
    <x-breadcrumbs :items="[['My requests', route('access.requests')], ['New request', route('requests.new')], ['Request access', null]]" />
    <h1 class="mt-6 text-3xl font-semibold tracking-tight">Request access</h1>
    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Your request goes to your head of department and the application owner for approval. You’ll be notified when it’s decided.</p>

    @if ($alreadyHasAccess)
        <p class="mt-6 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-900">You already have access to this application. <a href="{{ route('home') }}" class="font-medium underline">Open it from My applications.</a></p>
    @elseif ($alreadyPending)
        <p class="mt-6 rounded-xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-900">You already have a request for this application waiting for approval. <a href="{{ route('access.requests') }}" class="font-medium underline">See its progress.</a></p>
    @endif

    <form wire:submit="submit" class="mt-8 space-y-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800">
        <p class="text-xs text-zinc-500 dark:text-zinc-400"><span class="text-primary-600 dark:text-primary-400" aria-hidden="true">*</span> Required</p>
        <div>
            <label for="application" class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">Application<x-required /></label>
            <select wire:model.live="application" id="application" required
                    class="mt-2 block w-full rounded-xl border-0 bg-white py-3 pl-3 pr-10 text-sm text-zinc-900 shadow-sm ring-1 ring-zinc-300 focus:outline-none focus:ring-2 focus:ring-primary-600 dark:bg-zinc-900 dark:text-white dark:ring-zinc-700">
                <option value="">Choose an application…</option>
                @foreach ($available as $app)
                    <option value="{{ $app->slug }}">{{ $app->name }}</option>
                @endforeach
            </select>
            @error('application') <p class="mt-2 text-sm text-primary-700 dark:text-primary-400" role="alert">{{ $message }}</p> @enderror
            @if ($available->isEmpty())
                <p class="mt-2 text-sm text-zinc-500">You already have, or have requested, every available application.</p>
            @endif
        </div>

        @if ($selected)
            @if ($selected->description)
                <p class="-mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ $selected->description }}</p>
            @endif

            @if ($selectedRoles->count() > 1)
                <fieldset>
                    <legend class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Roles needed <span class="font-normal text-zinc-500">(optional)</span></legend>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach ($selectedRoles as $role)
                            <label wire:key="role-{{ $role->id }}" class="flex cursor-pointer items-start gap-3 rounded-xl p-3 ring-1 ring-zinc-200 transition has-[:checked]:bg-primary-50 has-[:checked]:ring-primary-300 dark:ring-zinc-700 dark:has-[:checked]:bg-primary-950/40 dark:has-[:checked]:ring-primary-800">
                                <input type="checkbox" wire:model="roles" value="{{ $role->id }}" class="mt-0.5 size-4 accent-primary-600">
                                <span>
                                    <span class="block text-sm font-medium">{{ $role->name }}</span>
                                    @if ($role->description)<span class="block text-xs text-zinc-500">{{ $role->description }}</span>@endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs text-zinc-500">If you’re not sure, leave these empty; you’ll get the standard access.</p>
                </fieldset>
            @endif

            <div>
                <label for="justification" class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">Why do you need access?<x-required /></label>
                <textarea wire:model="justification" id="justification" rows="3" required maxlength="1000"
                          placeholder="e.g. I have joined the HR department as an officer and process leave applications."
                          class="mt-2 block w-full rounded-xl border-0 bg-white p-3 text-sm text-zinc-900 shadow-sm ring-1 ring-zinc-300 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-primary-600 dark:bg-zinc-900 dark:text-white dark:ring-zinc-700"></textarea>
                @error('justification') <p class="mt-2 text-sm text-primary-700 dark:text-primary-400" role="alert">{{ $message }}</p> @enderror
            </div>

            <x-text-input wire:model="accessUntil" name="accessUntil" type="date" label="Access needed until (optional)" :min="now()->addDay()->toDateString()"
                          hint="Leave empty for ongoing access. Use an end date for temporary work." />

            <div class="rounded-xl bg-zinc-50 p-4 text-sm text-zinc-600 ring-1 ring-zinc-200 dark:bg-zinc-950 dark:text-zinc-400 dark:ring-zinc-800">
                <p class="font-medium text-zinc-800 dark:text-zinc-200">Who will approve</p>
                <ol class="mt-2 list-inside list-decimal space-y-1">
                    @if ($needsHod)
                        <li>Head of department{{ $hod ? ' — '.$hod->name : ' (none on record; an administrator will approve)' }}</li>
                    @endif
                    <li>Application owner{{ $selected->owner ? ' — '.$selected->owner->name : '' }}</li>
                    @if ($selected->approval_policy['require_it_security'] ?? false)
                        <li>IT security</li>
                    @endif
                </ol>
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
            <a href="{{ route('home') }}" class="inline-flex items-center justify-center gap-1.5 rounded-xl px-4 py-2.5 text-sm font-semibold text-zinc-700 ring-1 ring-zinc-300 transition hover:bg-zinc-50 dark:text-zinc-200 dark:ring-zinc-700 dark:hover:bg-zinc-800"><x-heroicon-m-arrow-left class="size-4" /> My applications</a>
                <x-primary-button target="submit" loading="Sending…" class="sm:!w-auto">Send request</x-primary-button>
            </div>
        @endif
    </form>
</div>
