<div class="mx-auto max-w-4xl">
    <h1 class="text-3xl font-semibold tracking-tight">Approvals</h1>
    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Confirm that the people below should still have access to your applications. Remove anyone who has left, changed role or no longer needs it.</p>
    <x-approval-tabs class="mt-6" />

    @if (session('status'))
        <p class="mt-6 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-900" role="status">{{ session('status') }}</p>
    @endif

    <div class="mt-8 space-y-6">
        @forelse ($groups as $items)
            @php($first = $items->first())
            <section wire:key="group-{{ $first->access_review_campaign_id }}-{{ $first->application_id }}" class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
                    <div>
                        <h2 class="font-semibold">{{ $first->application->name }}</h2>
                        <p class="text-sm text-zinc-500">
                            {{ $first->campaign->name }} · due {{ $first->campaign->due_at->format('D j M') }}
                            @if ($first->campaign->auto_revoke)
                                · <span class="text-primary-700 dark:text-primary-400">unconfirmed access will be removed</span>
                            @endif
                        </p>
                    </div>
                    <button type="button" wire:click="keepAll({{ $first->access_review_campaign_id }}, {{ $first->application_id }})"
                            wire:confirm="Confirm that all {{ $items->count() }} remaining people should keep access to {{ $first->application->name }}?"
                            class="rounded-xl px-3.5 py-2 text-sm font-medium text-zinc-700 ring-1 ring-zinc-300 transition hover:bg-zinc-50 dark:text-zinc-200 dark:ring-zinc-700 dark:hover:bg-zinc-800">
                        Keep all {{ $items->count() }}
                    </button>
                </header>

                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($items as $item)
                        <li wire:key="item-{{ $item->id }}" class="px-5 py-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="font-medium">{{ $item->user->name }}
                                        @if (! $item->user->status->canSignIn())
                                            <span class="ml-1 rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-950 dark:text-primary-300">Account {{ $item->user->status->value }}</span>
                                        @endif
                                    </p>
                                    <p class="text-sm text-zinc-500">
                                        {{ $item->user->username }}@if ($item->user->emp_id) · {{ $item->user->emp_id }}@endif
                                        @if ($item->user->department) · {{ $item->user->department->name }}@endif
                                        @if ($item->user->designation) · {{ $item->user->designation }}@endif
                                    </p>
                                    <p class="mt-1 text-xs text-zinc-500">
                                        Roles: {{ $item->roles_snapshot ? implode(', ', $item->roles_snapshot) : 'default' }}
                                        · access since {{ $item->access_since?->format('M Y') ?? '—' }}
                                        · <span @class(['text-amber-700 dark:text-amber-400' => ! $item->last_used_at || $item->last_used_at->lt(now()->subDays(90))])>
                                            last used {{ $item->last_used_at?->diffForHumans() ?? 'never' }}
                                        </span>
                                    </p>
                                </div>
                                @if ($removing !== $item->id)
                                    <div class="flex shrink-0 gap-2">
                                        <button type="button" wire:click="startRemove({{ $item->id }})"
                                                class="rounded-xl px-3.5 py-2 text-sm font-medium text-primary-700 ring-1 ring-primary-200 transition hover:bg-primary-50 dark:text-primary-300 dark:ring-primary-900 dark:hover:bg-primary-950">Remove</button>
                                        <button type="button" wire:click="keep({{ $item->id }})"
                                                class="rounded-xl bg-primary-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">Keep</button>
                                    </div>
                                @endif
                            </div>
                            @if ($removing === $item->id)
                                <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                    <label for="reason-{{ $item->id }}" class="sr-only">Reason</label>
                                    <input id="reason-{{ $item->id }}" type="text" wire:model="reasons.{{ $item->id }}" maxlength="250" placeholder="Reason (optional), e.g. moved to another department"
                                           class="block w-full rounded-xl border-0 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-zinc-300 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-primary-600 dark:bg-zinc-900 dark:ring-zinc-700">
                                    <div class="flex shrink-0 gap-2">
                                        <button type="button" wire:click="$set('removing', null)" class="rounded-xl px-3.5 py-2 text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-300">Cancel</button>
                                        <button type="button" wire:click="confirmRemove({{ $item->id }})"
                                                class="rounded-xl bg-primary-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">Remove access</button>
                                    </div>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <div class="rounded-2xl border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
                <x-heroicon-o-check-circle class="mx-auto size-8 text-emerald-500" />
                <p class="mt-3 font-medium">No access to review</p>
                <p class="mt-1 text-sm text-zinc-500">When an access review starts, the people using your applications will be listed here.</p>
            </div>
        @endforelse
    </div>

    @if ($decided->isNotEmpty())
        <h2 class="mt-12 text-lg font-semibold">Recently reviewed by you</h2>
        <ul class="mt-4 divide-y divide-zinc-100 rounded-2xl bg-white text-sm shadow-sm ring-1 ring-zinc-200 dark:divide-zinc-800 dark:bg-zinc-900 dark:ring-zinc-800">
            @foreach ($decided as $item)
                <li class="flex items-center justify-between gap-4 px-5 py-3">
                    <span>{{ $item->user->name }} · {{ $item->application->name }}</span>
                    <span class="flex items-center gap-3 text-zinc-500"><x-status-badge :status="$item->decision" /> {{ $item->decided_at?->format('j M') }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
