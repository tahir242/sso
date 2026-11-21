@use('App\Domain\Helpdesk\Enums\CommentKind')
@use('App\Domain\Helpdesk\Enums\TicketStatus')

@php
    $files = $ticket->getMedia('attachments');
    $filesFor = fn ($commentId) => $files->filter(fn ($m) => $m->getCustomProperty('comment_id') === $commentId);
    $isRequester = $ticket->requester_id === auth()->id();
@endphp

<div class="mx-auto max-w-3xl">
    <a href="{{ route('support') }}" class="inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">
        <x-heroicon-o-arrow-left class="size-4" /> My requests · IT help
    </a>

    <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400">
                {{ $ticket->reference }} · {{ $ticket->application?->name ?? 'General IT' }}@if ($ticket->category) · {{ $ticket->category->name }}@endif
            </p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ $ticket->subject }}</h1>
            <p class="mt-1 text-sm text-zinc-500">
                {{ $ticket->priority->getLabel() }} priority · raised {{ $ticket->created_at->format('j M Y, H:i') }}
                @unless ($isRequester) by {{ $ticket->requester->name }} @endunless
                · {{ $ticket->assignee ? $ticket->assignee->name.' is handling it' : 'not yet assigned' }}
            </p>
        </div>
        <x-status-badge :status="$ticket->status" />
    </div>

    @if (session('status'))
        <p class="mt-6 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-900" role="status">{{ session('status') }}</p>
    @endif

    @if ($isStaff)
        <p class="mt-6 rounded-xl bg-zinc-100 p-3 text-sm text-zinc-600 dark:bg-zinc-900 dark:text-zinc-400">
            You handle this ticket. Internal notes, assignment and status are in the
            <a href="{{ url('/admin/tickets/'.$ticket->ulid) }}" class="font-medium text-primary-700 underline dark:text-primary-400">admin console</a>.
        </p>
    @endif

    @if ($ticket->status === TicketStatus::Resolved && $isRequester)
        <div class="mt-6 flex flex-col gap-3 rounded-2xl bg-emerald-50 p-5 ring-1 ring-emerald-200 sm:flex-row sm:items-center sm:justify-between dark:bg-emerald-950/40 dark:ring-emerald-900">
            <p class="text-sm text-emerald-900 dark:text-emerald-100">The helpdesk marked this resolved. Is it working for you?</p>
            <div class="flex shrink-0 gap-2">
                <button type="button" wire:click="close" class="rounded-xl bg-emerald-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Yes, close it</button>
                <button type="button" wire:click="reopen" class="rounded-xl px-3.5 py-2 text-sm font-medium text-emerald-900 ring-1 ring-emerald-300 hover:bg-emerald-100 dark:text-emerald-100 dark:ring-emerald-800 dark:hover:bg-emerald-900">No, reopen</button>
            </div>
        </div>
    @endif

    <ol class="mt-8 space-y-4">
        {{-- The original request --}}
        <li class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <p class="text-sm"><span class="font-semibold">{{ $ticket->requester->name }}</span> <span class="text-zinc-500">· {{ $ticket->created_at->format('j M, H:i') }}</span></p>
            <p class="mt-2 whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $ticket->description }}</p>
            <x-ticket-files :files="$filesFor(null)" />
        </li>

        @foreach ($ticket->publicComments as $comment)
            @if ($comment->kind === CommentKind::Event)
                <li wire:key="c-{{ $comment->id }}" class="flex items-center gap-2 px-2 text-xs text-zinc-500">
                    <x-heroicon-o-arrow-path class="size-4" /> {{ $comment->body }} · {{ $comment->author->name }} · {{ $comment->created_at->format('j M, H:i') }}
                </li>
            @else
                @php $fromRequester = $comment->user_id === $ticket->requester_id; @endphp
                <li wire:key="c-{{ $comment->id }}" @class([
                    'rounded-2xl p-5 shadow-sm ring-1',
                    'bg-white ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800' => $fromRequester,
                    'bg-primary-50/60 ring-primary-100 dark:bg-primary-950/30 dark:ring-primary-900' => ! $fromRequester,
                ])>
                    <p class="text-sm">
                        <span class="font-semibold">{{ $comment->author->name }}</span>
                        @unless ($fromRequester) <span class="rounded bg-primary-600 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-white">IT</span> @endunless
                        <span class="text-zinc-500">· {{ $comment->created_at->format('j M, H:i') }}</span>
                    </p>
                    <p class="mt-2 whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $comment->body }}</p>
                    <x-ticket-files :files="$filesFor($comment->id)" />
                </li>
            @endif
        @endforeach
    </ol>

    @if ($ticket->status !== TicketStatus::Closed)
        <form wire:submit="reply" class="mt-6 space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800" novalidate>
            <label for="body" class="block text-sm font-medium">Reply</label>
            <textarea wire:model="body" id="body" rows="4" maxlength="5000"
                      class="block w-full rounded-xl border-0 bg-white p-3 text-sm shadow-sm ring-1 ring-zinc-300 focus:outline-none focus:ring-2 focus:ring-primary-600 dark:bg-zinc-900 dark:ring-zinc-700"></textarea>
            @error('body') <p class="text-sm text-primary-700 dark:text-primary-400" role="alert">{{ $message }}</p> @enderror
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <input wire:model="attachments" type="file" multiple accept=".png,.jpg,.jpeg,.webp,.pdf" aria-label="Attach files"
                           class="block w-full text-sm text-zinc-600 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200 dark:text-zinc-400 dark:file:bg-zinc-800 dark:file:text-zinc-200">
                    @error('attachments.*') <p class="mt-1 text-sm text-primary-700 dark:text-primary-400" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="flex shrink-0 justify-end gap-2">
                    @if ($isRequester && $ticket->status !== TicketStatus::Resolved)
                        <button type="button" wire:click="close" wire:confirm="Close this ticket? Only do this if the problem is solved."
                                class="rounded-xl px-3.5 py-2 text-sm font-medium text-zinc-700 ring-1 ring-zinc-300 transition hover:bg-zinc-50 dark:text-zinc-200 dark:ring-zinc-700 dark:hover:bg-zinc-800">My problem is solved</button>
                    @endif
                    <x-primary-button target="reply" loading="Sending…" class="sm:!w-auto">Send reply</x-primary-button>
                </div>
            </div>
        </form>
    @else
        <p class="mt-6 rounded-xl bg-zinc-100 p-4 text-center text-sm text-zinc-600 dark:bg-zinc-900 dark:text-zinc-400">
            This ticket is closed. Still having trouble? <a href="{{ route('support.new', ['app' => $ticket->application?->slug]) }}" class="font-medium text-primary-700 underline dark:text-primary-400">Raise a new ticket</a>.
        </p>
    @endif
</div>
