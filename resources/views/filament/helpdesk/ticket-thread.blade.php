@use('App\Domain\Helpdesk\Enums\CommentKind')

@php
    /** @var \App\Domain\Helpdesk\Models\Ticket $ticket */
    $ticket = $getRecord()->loadMissing(['requester', 'media', 'comments.author']);
    $files = $ticket->getMedia('attachments');
    $filesFor = fn ($commentId) => $files->filter(fn ($m) => $m->getCustomProperty('comment_id') === $commentId);
@endphp

<div class="space-y-4">
    <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
        <p class="text-sm"><span class="font-semibold">{{ $ticket->requester->name }}</span> <span class="text-gray-500">· {{ $ticket->created_at->format('j M Y, H:i') }}</span></p>
        <p class="mt-2 whitespace-pre-line text-sm">{{ $ticket->description }}</p>
        @include('filament.helpdesk.ticket-files', ['items' => $filesFor(null)])
    </div>

    @foreach ($ticket->comments as $comment)
        @if ($comment->kind === CommentKind::Event)
            <p class="flex items-center gap-2 px-1 text-xs text-gray-500">
                <x-filament::icon icon="heroicon-o-arrow-path" class="size-4" />
                {{ $comment->body }} · {{ $comment->author->name }} · {{ $comment->created_at->format('j M, H:i') }}
            </p>
        @else
            @php
                $note = $comment->kind === CommentKind::Note;
                $fromRequester = $comment->user_id === $ticket->requester_id;
            @endphp
            <div @class([
                'rounded-xl p-4 ring-1',
                'bg-amber-50 ring-amber-200 dark:bg-amber-500/10 dark:ring-amber-500/30' => $note,
                'bg-gray-50 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10' => ! $note && $fromRequester,
                'bg-primary-50 ring-primary-200 dark:bg-primary-500/10 dark:ring-primary-500/30' => ! $note && ! $fromRequester,
            ])>
                <p class="text-sm">
                    <span class="font-semibold">{{ $comment->author->name }}</span>
                    @if ($note)
                        <x-filament::badge color="warning" size="sm" class="ms-1 inline-flex">Internal note</x-filament::badge>
                    @endif
                    <span class="text-gray-500">· {{ $comment->created_at->format('j M Y, H:i') }}</span>
                </p>
                <p class="mt-2 whitespace-pre-line text-sm">{{ $comment->body }}</p>
                @include('filament.helpdesk.ticket-files', ['items' => $filesFor($comment->id)])
            </div>
        @endif
    @endforeach
</div>
