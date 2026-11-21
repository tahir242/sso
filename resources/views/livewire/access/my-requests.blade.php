<div class="mx-auto max-w-3xl">
    <x-my-requests-header :active="$tab === 'changes' ? 'suggestions' : 'access'" />

    @if (session('status'))
        <p class="mt-6 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-900" role="status">{{ session('status') }}</p>
    @endif

    <div class="mt-8 space-y-4">
        @if ($tab === 'access')
            @forelse ($requests as $request)
                <article wire:key="req-{{ $request->ulid }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="font-semibold">{{ $request->application->name }}</h2>
                            <p class="text-sm text-zinc-500">
                                Requested {{ $request->created_at->format('j M Y') }}
                                @if ($request->user_id !== auth()->id()) for {{ $request->user->name }} @endif
                                @if ($request->access_until) · until {{ $request->access_until->format('j M Y') }} @endif
                            </p>
                        </div>
                        <x-status-badge :status="$request->status" />
                    </div>
                    <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">{{ $request->justification }}</p>
                    <x-request-timeline :request="$request" class="mt-4 border-t border-zinc-100 pt-4 dark:border-zinc-800" />
                    @if ($request->status->isOpen())
                        <div class="mt-4 flex justify-end">
                            <button type="button" wire:click="cancel('{{ $request->ulid }}')" wire:confirm="Cancel this request?"
                                    class="rounded-xl px-3.5 py-2 text-sm font-medium text-zinc-700 ring-1 ring-zinc-300 transition hover:bg-zinc-50 dark:text-zinc-200 dark:ring-zinc-700 dark:hover:bg-zinc-800">Cancel request</button>
                        </div>
                    @endif
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="font-medium">No access requests yet</p>
                    <p class="mt-1 text-sm text-zinc-500">Need an application you can’t see in My applications? <a href="{{ route('access.request') }}" class="text-primary-700 underline dark:text-primary-400">Request access</a>.</p>
                </div>
            @endforelse
        @else
            @forelse ($changes as $change)
                <article wire:key="cr-{{ $change->ulid }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400">{{ $change->application->name }} · {{ $change->type->getLabel() }}</p>
                            <h2 class="mt-1 font-semibold">{{ $change->title }}</h2>
                            <p class="text-sm text-zinc-500">Sent {{ $change->created_at->format('j M Y') }} · {{ $change->priority->getLabel() }} priority</p>
                        </div>
                        <x-status-badge :status="$change->status" />
                    </div>
                    <p class="mt-3 whitespace-pre-line text-sm text-zinc-600 dark:text-zinc-400">{{ Str::limit($change->description, 400) }}</p>
                    @if ($change->getMedia('attachments')->isNotEmpty())
                        <ul class="mt-3 flex flex-wrap gap-2 text-sm">
                            @foreach ($change->getMedia('attachments') as $file)
                                <li><a href="{{ $file->getUrl() }}" target="_blank" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-primary-700 ring-1 ring-zinc-200 hover:bg-zinc-50 dark:text-primary-400 dark:ring-zinc-700 dark:hover:bg-zinc-800"><x-heroicon-o-paper-clip class="size-4" /> {{ $file->name }}</a></li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($change->owner_comment)
                        <p class="mt-4 rounded-xl bg-zinc-50 p-3 text-sm ring-1 ring-zinc-200 dark:bg-zinc-950 dark:ring-zinc-800">
                            <span class="font-medium">Reply from the {{ $change->application->name }} team:</span> {{ $change->owner_comment }}
                        </p>
                    @endif
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="font-medium">No suggestions yet</p>
                    <p class="mt-1 text-sm text-zinc-500">Have an idea that would make an application better? <a href="{{ route('suggestions.new') }}" class="text-primary-700 underline dark:text-primary-400">Suggest an improvement</a>.</p>
                </div>
            @endforelse
        @endif
    </div>
</div>
