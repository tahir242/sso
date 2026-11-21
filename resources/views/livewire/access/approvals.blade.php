<div class="mx-auto max-w-3xl">
    <h1 class="text-3xl font-semibold tracking-tight">Approvals</h1>
    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Access requests waiting for your decision.</p>
    <x-approval-tabs class="mt-6" />

    @if (session('status'))
        <p class="mt-6 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-900" role="status">{{ session('status') }}</p>
    @endif

    <div class="mt-8 space-y-4">
        @forelse ($waiting as $step)
            @php($request = $step->request)
            <article wire:key="step-{{ $step->id }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-400">{{ $step->type->getLabel() }} approval</p>
                        <h2 class="mt-1 font-semibold">{{ $request->user->name }} → {{ $request->application->name }}</h2>
                        <p class="text-sm text-zinc-500">
                            {{ $request->user->username }}@if ($request->user->emp_id) · {{ $request->user->emp_id }}@endif
                            @if ($request->user->department) · {{ $request->user->department->name }}@endif
                            @if ($request->user->designation) · {{ $request->user->designation }}@endif
                        </p>
                    </div>
                    <span @class([
                        'rounded-full px-2.5 py-1 text-xs font-medium ring-1',
                        'bg-primary-50 text-primary-700 ring-primary-200 dark:bg-primary-950 dark:text-primary-300 dark:ring-primary-900' => $step->isOverdue(),
                        'bg-zinc-100 text-zinc-600 ring-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-700' => ! $step->isOverdue(),
                    ])>{{ $step->isOverdue() ? 'Overdue' : 'Due '.$step->due_at?->diffForHumans() }}</span>
                </div>

                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <dt class="text-zinc-500">Reason</dt>
                        <dd class="mt-0.5">{{ $request->justification }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Roles</dt>
                        <dd class="mt-0.5">{{ $request->requestedRoles()->pluck('name')->join(', ') ?: 'Standard access' }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Until</dt>
                        <dd class="mt-0.5">{{ $request->access_until?->format('j M Y') ?? 'Ongoing' }}</dd>
                    </div>
                    @if ($request->requested_by !== $request->user_id)
                        <div><dt class="text-zinc-500">Requested by</dt><dd class="mt-0.5">{{ $request->requester->name }}</dd></div>
                    @endif
                </dl>

                <details class="mt-4">
                    <summary class="cursor-pointer text-sm font-medium text-zinc-600 dark:text-zinc-400">Approval progress</summary>
                    <x-request-timeline :request="$request" class="mt-3" />
                </details>

                <div class="mt-4 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                    <label for="comment-{{ $step->id }}" class="sr-only">Comment</label>
                    <textarea wire:model="comments.{{ $step->id }}" id="comment-{{ $step->id }}" rows="2" maxlength="500"
                              placeholder="Comment (required when rejecting)"
                              class="block w-full rounded-xl border-0 bg-white p-3 text-sm shadow-sm ring-1 ring-zinc-300 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-primary-600 dark:bg-zinc-900 dark:ring-zinc-700"></textarea>
                    @error("comments.{$step->id}") <p class="mt-2 text-sm text-primary-700 dark:text-primary-400" role="alert">{{ $message }}</p> @enderror
                    <div class="mt-3 flex justify-end gap-2">
                        <button type="button" wire:click="reject({{ $step->id }})" wire:loading.attr="disabled"
                                class="rounded-xl px-4 py-2 text-sm font-medium text-primary-700 ring-1 ring-primary-200 transition hover:bg-primary-50 dark:text-primary-300 dark:ring-primary-900 dark:hover:bg-primary-950">Reject</button>
                        <button type="button" wire:click="approve({{ $step->id }})" wire:loading.attr="disabled"
                                class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">Approve</button>
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
                <x-heroicon-o-check-circle class="mx-auto size-8 text-emerald-500" />
                <p class="mt-3 font-medium">Nothing waiting for you</p>
            </div>
        @endforelse
    </div>

    @if ($recent->isNotEmpty())
        <h2 class="mt-12 text-lg font-semibold">Recently decided by you</h2>
        <ul class="mt-4 divide-y divide-zinc-100 rounded-2xl bg-white text-sm shadow-sm ring-1 ring-zinc-200 dark:divide-zinc-800 dark:bg-zinc-900 dark:ring-zinc-800">
            @foreach ($recent as $step)
                <li class="flex items-center justify-between gap-4 px-5 py-3">
                    <span>{{ $step->request->user->name }} → {{ $step->request->application->name }}</span>
                    <span class="flex items-center gap-3 text-zinc-500">
                        <x-status-badge :status="$step->status" />
                        {{ $step->decided_at->format('j M') }}
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
