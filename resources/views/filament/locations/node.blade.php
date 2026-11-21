@php
    /** @var \App\Domain\Identity\Models\Location $node */
    $children = $byParent->get($node->id, collect())->filter(fn ($c) => $visible === null || isset($visible[$c->id]));
    $people = $usersByLocation->get($node->id, collect());
    $hasContent = $children->isNotEmpty() || $people->isNotEmpty();
    $isSelected = $selected?->id === $node->id;
    $startOpen = $visible !== null || $depth < 2 || ($selected && in_array($node->id, array_map(fn ($l) => $l->id, $details['chain'] ?? []), true));
@endphp

<li x-data="{ open: @js($startOpen) }" role="treeitem" aria-expanded="{{ $hasContent ? 'true' : 'false' }}" x-bind:aria-expanded="@js($hasContent) ? open.toString() : 'false'" aria-selected="{{ $isSelected ? 'true' : 'false' }}">
    <div @class([
        'group flex items-center gap-1 rounded-lg pr-2 transition',
        'bg-primary-50 ring-1 ring-primary-200 dark:bg-primary-500/10 dark:ring-primary-500/30' => $isSelected,
        'hover:bg-gray-50 dark:hover:bg-white/5' => ! $isSelected,
    ])>
        <button type="button" x-on:click="open = !open" @class(['grid size-7 shrink-0 place-items-center rounded-md text-gray-400 hover:text-gray-700 dark:hover:text-gray-200', 'invisible' => ! $hasContent]) x-bind:aria-label="open ? 'Collapse' : 'Expand'">
            <x-filament::icon icon="heroicon-m-chevron-right" class="size-4 transition-transform duration-200" x-bind:class="open && 'rotate-90'" />
        </button>
        <button type="button" wire:click="select({{ $node->id }})" class="flex min-w-0 flex-1 items-center gap-2 py-1.5 text-left">
            <span class="grid size-7 shrink-0 place-items-center rounded-md ring-1 {{ $node->type->color() }}">
                <x-filament::icon :icon="$node->type->icon()" class="size-4" />
            </span>
            <span @class(['truncate text-sm', 'font-semibold text-gray-950 dark:text-white' => $isSelected, 'font-medium text-gray-700 dark:text-gray-200' => ! $isSelected, 'line-through opacity-60' => ! $node->active])>
                @if ($term !== '' && str_contains(\Illuminate\Support\Str::lower($node->name), $term))
                    {!! preg_replace('/('.preg_quote(e($term), '/').')/i', '<mark class="rounded bg-amber-200/70 px-0.5 text-inherit dark:bg-amber-400/30">$1</mark>', e($node->name)) !!}
                @else
                    {{ $node->name }}
                @endif
            </span>
            @if ($node->code)
                <span class="hidden shrink-0 rounded bg-gray-100 px-1.5 font-mono text-[11px] text-gray-500 sm:inline dark:bg-white/10 dark:text-gray-400">{{ $node->code }}</span>
            @endif
        </button>
        @if ($node->users_count)
            <span class="shrink-0 rounded-full bg-gray-100 px-2 text-xs font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300" title="{{ $node->users_count }} {{ \Illuminate\Support\Str::plural('person', $node->users_count) }} here">
                {{ $node->users_count }}
            </span>
        @endif
        @if ($canManage && $node->type->child())
            <button type="button" wire:click="mountAction('create', @js(['parent' => $node->id]))" class="grid size-7 shrink-0 place-items-center rounded-md text-gray-400 opacity-0 transition hover:bg-white hover:text-primary-600 focus:opacity-100 group-hover:opacity-100 dark:hover:bg-white/10" title="Add {{ $node->type->child()->value }}" aria-label="Add {{ $node->type->child()->value }} in {{ $node->name }}">
                <x-filament::icon icon="heroicon-m-plus" class="size-4" />
            </button>
        @endif
    </div>

    @if ($hasContent)
        <ul x-show="open" x-collapse role="group" class="ml-3.5 border-l border-gray-200 pl-2 dark:border-white/10">
            @foreach ($children as $child)
                @include('filament.locations.node', ['node' => $child, 'depth' => $depth + 1])
            @endforeach
            @foreach ($people->take(25) as $person)
                <li role="treeitem" class="flex items-center gap-2 py-1 pl-8 pr-2 text-sm text-gray-600 dark:text-gray-300">
                    <span class="grid size-6 shrink-0 place-items-center rounded-full bg-gradient-to-br from-gray-400 to-gray-600 text-[10px] font-semibold text-white" aria-hidden="true">{{ \App\Domain\Identity\Auth\RecentAccounts::initials($person->name ?: $person->username) }}</span>
                    <span class="truncate">{{ $person->name }}</span>
                    <span class="truncate text-xs text-gray-400">{{ $person->designation }}</span>
                </li>
            @endforeach
            @if ($people->count() > 25)
                <li class="py-1 pl-8 text-xs text-gray-400">and {{ $people->count() - 25 }} more…</li>
            @endif
        </ul>
    @endif
</li>
