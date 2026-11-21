<div class="mx-auto max-w-3xl">
    <x-my-requests-header active="help" />

    @if ($waiting)
        <p class="mt-6 flex items-center gap-2 rounded-xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-900" role="status">
            <x-heroicon-o-chat-bubble-left-ellipsis class="size-5 shrink-0" />
            {{ trans_choice('{1} The helpdesk is waiting for your answer on one ticket.|[2,*] The helpdesk is waiting for your answer on :count tickets.', $waiting) }}
        </p>
    @endif

    {{-- Open / closed: a small toggle, so it does not look like a second row of tabs. --}}
    <div class="mt-6 inline-flex rounded-xl bg-zinc-100 p-1 dark:bg-zinc-900" role="group" aria-label="Show tickets">
        @foreach (['active' => 'Open', 'closed' => 'Closed'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" aria-pressed="{{ $tab === $key ? 'true' : 'false' }}" @class([
                'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                'bg-white text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-white' => $tab === $key,
                'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $tab !== $key,
            ])>{{ $label }}</button>
        @endforeach
    </div>

    <ul class="mt-4 space-y-3">
        @forelse ($tickets as $ticket)
            <li wire:key="t-{{ $ticket->ulid }}">
                <a href="{{ route('support.show', $ticket->ulid) }}" class="block rounded-2xl bg-white p-5 shadow-sm ring-1 ring-zinc-200 transition hover:ring-primary-300 dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:ring-primary-800">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400">
                                {{ $ticket->reference }} · {{ $ticket->application?->name ?? 'General IT' }}@if ($ticket->category) · {{ $ticket->category->name }}@endif
                            </p>
                            <h2 class="mt-1 truncate font-semibold">{{ $ticket->subject }}</h2>
                            <p class="text-sm text-zinc-500">
                                Raised {{ $ticket->created_at->format('j M Y') }} · updated {{ $ticket->last_activity_at?->diffForHumans() }}
                                @if ($ticket->assignee) · {{ $ticket->assignee->name }} is on it @endif
                            </p>
                        </div>
                        <x-status-badge :status="$ticket->status" />
                    </div>
                </a>
            </li>
        @empty
            <li class="rounded-2xl border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
                <p class="font-medium">{{ $tab === 'active' ? 'No open tickets' : 'No closed tickets' }}</p>
                <p class="mt-1 text-sm text-zinc-500">Something not working? <a href="{{ route('support.new') }}" class="text-primary-700 underline dark:text-primary-400">Tell the IT helpdesk</a>.</p>
            </li>
        @endforelse
    </ul>
</div>
