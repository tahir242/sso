<div wire:poll.60s class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
    <button type="button" x-on:click="open = !open" aria-label="Notifications{{ $unread ? " ({$unread} unread)" : '' }}" x-bind:aria-expanded="open"
            class="relative inline-flex size-9 items-center justify-center rounded-full text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white">
        <x-heroicon-o-bell class="size-5" />
        @if ($unread)
            <span class="absolute right-1 top-1 grid min-w-4 place-items-center rounded-full bg-primary-600 px-1 text-[10px] font-semibold leading-4 text-white">{{ $unread > 9 ? '9+' : $unread }}</span>
        @endif
    </button>

    <div x-show="open" x-cloak x-transition.origin.top.right
         class="absolute right-0 z-40 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl bg-white shadow-lg ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800">
        <div class="flex items-center justify-between border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
            <p class="text-sm font-semibold">Notifications</p>
            @if ($unread)
                <button type="button" wire:click="markAllRead" class="text-xs font-medium text-primary-700 hover:underline dark:text-primary-400">Mark all read</button>
            @endif
        </div>
        <ul class="max-h-96 divide-y divide-zinc-100 overflow-y-auto dark:divide-zinc-800">
            @forelse ($items as $item)
                <li>
                    <button type="button" wire:click="open('{{ $item->id }}')" class="flex w-full gap-3 px-4 py-3 text-left transition hover:bg-zinc-50 dark:hover:bg-zinc-800">
                        <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-primary-600' => ! $item->read_at, 'bg-transparent' => $item->read_at])></span>
                        <span class="min-w-0">
                            <span class="block text-sm font-medium">{{ $item->data['title'] ?? '' }}</span>
                            <span class="block text-sm text-zinc-500 dark:text-zinc-400">{{ $item->data['body'] ?? '' }}</span>
                            <span class="mt-0.5 block text-xs text-zinc-400">{{ $item->created_at->diffForHumans() }}</span>
                        </span>
                    </button>
                </li>
            @empty
                <li class="px-4 py-8 text-center text-sm text-zinc-500">You’re all caught up.</li>
            @endforelse
        </ul>
    </div>
</div>
