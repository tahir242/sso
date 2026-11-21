<div class="mx-auto max-w-3xl">
    <h1 class="text-3xl font-semibold tracking-tight">Approvals</h1>
    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Confirm that these people are joining your department. The IT helpdesk then creates their accounts.</p>
    <x-approval-tabs class="mt-6" />

    @if (session('status'))
        <p class="mt-6 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-900" role="status">{{ session('status') }}</p>
    @endif

    <div class="mt-8 space-y-4">
        @forelse ($pending as $request)
            <article wire:key="ns-{{ $request->id }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">{{ $request->full_name }}</h2>
                        <p class="text-sm text-zinc-500">{{ $request->designation }} · {{ $request->department->name }}@if ($request->emp_id) · {{ $request->emp_id }}@endif</p>
                    </div>
                    <span class="text-xs text-zinc-500">Submitted {{ $request->created_at->diffForHumans() }}</span>
                </div>
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-zinc-500">Joining</dt><dd class="mt-0.5">{{ $request->joining_date?->format('j M Y') ?? '—' }}</dd></div>
                    <div><dt class="text-zinc-500">Contact</dt><dd class="mt-0.5">{{ collect([$request->email, $request->mobile])->filter()->join(' · ') ?: '—' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-zinc-500">Applications</dt><dd class="mt-0.5">{{ $request->requestedApplications()->pluck('name')->join(', ') ?: 'None requested' }}</dd></div>
                    @if ($request->justification)
                        <div class="sm:col-span-2"><dt class="text-zinc-500">Note</dt><dd class="mt-0.5">{{ $request->justification }}</dd></div>
                    @endif
                </dl>
                <div class="mt-4 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                    <label for="ns-comment-{{ $request->id }}" class="sr-only">Comment</label>
                    <textarea wire:model="comments.{{ $request->id }}" id="ns-comment-{{ $request->id }}" rows="2" maxlength="500" placeholder="Comment (required when rejecting)"
                              class="block w-full rounded-xl border-0 bg-white p-3 text-sm shadow-sm ring-1 ring-zinc-300 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-primary-600 dark:bg-zinc-900 dark:ring-zinc-700"></textarea>
                    @error("comments.{$request->id}") <p class="mt-2 text-sm text-primary-700 dark:text-primary-400" role="alert">{{ $message }}</p> @enderror
                    <div class="mt-3 flex justify-end gap-2">
                        <button type="button" wire:click="decide({{ $request->id }}, false)" class="rounded-xl px-4 py-2 text-sm font-medium text-primary-700 ring-1 ring-primary-200 transition hover:bg-primary-50 dark:text-primary-300 dark:ring-primary-900 dark:hover:bg-primary-950">Not my staff / reject</button>
                        <button type="button" wire:click="decide({{ $request->id }}, true)" class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">Confirm</button>
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
                <x-heroicon-o-check-circle class="mx-auto size-8 text-emerald-500" />
                <p class="mt-3 font-medium">No new staff waiting</p>
            </div>
        @endforelse
    </div>
</div>
