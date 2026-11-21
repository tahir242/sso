<x-filament-panels::page>
    {{-- Summary --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 2xl:grid-cols-7">
        @foreach (\App\Domain\Identity\Enums\LocationType::cases() as $type)
            <div class="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <span class="grid size-9 place-items-center rounded-lg ring-1 {{ $type->color() }}"><x-filament::icon :icon="$type->icon()" class="size-5" /></span>
                <div>
                    <p class="text-xl font-semibold leading-none text-gray-950 dark:text-white">{{ $totals[$type->value] }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $type->plural() }}</p>
                </div>
            </div>
        @endforeach
        <div class="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <span class="grid size-9 place-items-center rounded-lg bg-primary-50 text-primary-600 ring-1 ring-primary-200 dark:bg-primary-500/10 dark:text-primary-400 dark:ring-primary-500/30"><x-filament::icon icon="heroicon-o-users" class="size-5" /></span>
            <div>
                <p class="text-xl font-semibold leading-none text-gray-950 dark:text-white">{{ $placedUsers }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">People placed</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <span class="grid size-9 place-items-center rounded-lg bg-gray-100 text-gray-500 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10"><x-filament::icon icon="heroicon-o-question-mark-circle" class="size-5" /></span>
            <div>
                <p class="text-xl font-semibold leading-none text-gray-950 dark:text-white">{{ $unplacedUsers }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Not placed yet</p>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
        {{-- Tree --}}
        <section class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 lg:col-span-3 dark:bg-gray-900 dark:ring-white/10" aria-label="Location tree">
            <div class="flex flex-wrap items-center gap-3 border-b border-gray-950/5 p-3 dark:border-white/10">
                <div class="min-w-48 flex-1">
                    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                        <x-filament::input type="search" wire:model.live.debounce.300ms="search" placeholder="Find a place, code or person" aria-label="Find a place, code or person" />
                    </x-filament::input.wrapper>
                </div>
                @if ($canManage)
                    <x-filament::button icon="heroicon-m-plus" wire:click="mountAction('create')">Add city</x-filament::button>
                @endif
            </div>

            <div class="max-h-[40rem] overflow-y-auto p-3">
                @php($rootNodes = $roots->filter(fn ($r) => $visible === null || isset($visible[$r->id])))
                @if ($rootNodes->isEmpty())
                    <div class="px-6 py-16 text-center">
                        <x-filament::icon icon="heroicon-o-map" class="mx-auto size-10 text-gray-300 dark:text-gray-600" />
                        @if ($term !== '')
                            <p class="mt-3 font-medium text-gray-950 dark:text-white">Nothing matches “{{ $search }}”</p>
                        @else
                            <p class="mt-3 font-medium text-gray-950 dark:text-white">No locations yet</p>
                            <p class="mt-1 text-sm text-gray-500">Start with a city, then add its buildings, floors, rooms and workstations.</p>
                        @endif
                    </div>
                @else
                    <ul role="tree" aria-label="Locations" class="space-y-0.5">
                        @foreach ($rootNodes as $node)
                            @include('filament.locations.node', ['node' => $node, 'depth' => 0])
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        {{-- Details --}}
        <aside class="lg:col-span-2">
            <div class="sticky top-20 rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                @if ($selected)
                    <div class="border-b border-gray-950/5 p-5 dark:border-white/10">
                        <nav aria-label="Path" class="flex flex-wrap items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                            @foreach ($details['chain'] as $crumb)
                                @if (! $loop->last)
                                    <button type="button" wire:click="select({{ $crumb->id }})" class="hover:text-primary-600 hover:underline">{{ $crumb->name }}</button>
                                    <x-filament::icon icon="heroicon-m-chevron-right" class="size-3" />
                                @endif
                            @endforeach
                        </nav>
                        <div class="mt-3 flex items-start gap-3">
                            <span class="grid size-12 shrink-0 place-items-center rounded-xl ring-1 {{ $selected->type->color() }}"><x-filament::icon :icon="$selected->type->icon()" class="size-6" /></span>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $selected->type->getLabel() }}@if (! $selected->active) · inactive @endif</p>
                                <h2 class="truncate text-lg font-semibold text-gray-950 dark:text-white">{{ $selected->name }}</h2>
                                @if ($selected->code)<p class="font-mono text-xs text-gray-500">{{ $selected->code }}</p>@endif
                            </div>
                        </div>
                        @if ($selected->description)
                            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">{{ $selected->description }}</p>
                        @endif

                        <dl class="mt-4 grid grid-cols-2 gap-2 text-center">
                            <div class="rounded-lg bg-gray-50 p-2 dark:bg-white/5"><dt class="text-xs text-gray-500">People inside</dt><dd class="text-lg font-semibold text-gray-950 dark:text-white">{{ $details['usersInside'] }}</dd></div>
                            <div class="rounded-lg bg-gray-50 p-2 dark:bg-white/5"><dt class="text-xs text-gray-500">Places inside</dt><dd class="text-lg font-semibold text-gray-950 dark:text-white">{{ array_sum($details['counts']) }}</dd></div>
                        </dl>
                        @if ($details['counts'])
                            <p class="mt-2 text-xs text-gray-500">@foreach ($details['counts'] as $label => $n){{ $n }} {{ \Illuminate\Support\Str::lower($n === 1 ? \Illuminate\Support\Str::singular($label) : $label) }}@if (! $loop->last) · @endif @endforeach</p>
                        @endif

                        @if ($canManage)
                            <div class="mt-4 flex flex-wrap gap-2">
                                @if ($selected->type->child())
                                    <x-filament::button size="sm" icon="heroicon-m-plus" wire:click="mountAction('create', { parent: {{ $selected->id }} })">Add {{ $selected->type->child()->value }}</x-filament::button>
                                @endif
                                <x-filament::button size="sm" color="gray" icon="heroicon-m-user-plus" wire:click="mountAction('assignUsers', { id: {{ $selected->id }} })">Place users</x-filament::button>
                                <x-filament::button size="sm" color="gray" icon="heroicon-m-pencil-square" wire:click="mountAction('edit', { id: {{ $selected->id }} })">Edit</x-filament::button>
                                @if ($selected->type->parent())
                                    <x-filament::button size="sm" color="gray" icon="heroicon-m-arrows-right-left" wire:click="mountAction('move', { id: {{ $selected->id }} })">Move</x-filament::button>
                                @endif
                                <x-filament::button size="sm" color="danger" outlined icon="heroicon-m-trash" wire:click="mountAction('delete', { id: {{ $selected->id }} })">Delete</x-filament::button>
                            </div>
                        @endif
                    </div>

                    <div class="p-5">
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">People here ({{ $details['users']->count() }})</h3>
                        @if ($details['users']->isEmpty())
                            <p class="mt-2 text-sm text-gray-500">Nobody is placed directly at this {{ $selected->type->value }}.</p>
                        @else
                            <ul class="mt-3 max-h-80 space-y-1 overflow-y-auto">
                                @foreach ($details['users'] as $person)
                                    <li class="flex items-center gap-3 rounded-lg p-1.5 hover:bg-gray-50 dark:hover:bg-white/5">
                                        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-gradient-to-br from-primary-500 to-primary-800 text-xs font-semibold text-white" aria-hidden="true">{{ \App\Domain\Identity\Auth\RecentAccounts::initials($person->name ?: $person->username) }}</span>
                                        <div class="min-w-0 flex-1">
                                            <a href="{{ \App\Filament\Resources\Users\UserResource::getUrl('edit', ['record' => $person]) }}" class="block truncate text-sm font-medium text-gray-950 hover:text-primary-600 dark:text-white">{{ $person->name }}</a>
                                            <p class="truncate text-xs text-gray-500">{{ collect([$person->designation, $person->department?->name])->filter()->implode(' · ') ?: $person->username }}</p>
                                        </div>
                                        @if ($canManage)
                                            {{ ($this->removeUserAction)(['user' => $person->id]) }}
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @else
                    <div class="px-6 py-16 text-center">
                        <x-filament::icon icon="heroicon-o-cursor-arrow-rays" class="mx-auto size-10 text-gray-300 dark:text-gray-600" />
                        <p class="mt-3 font-medium text-gray-950 dark:text-white">Select a place</p>
                        <p class="mt-1 text-sm text-gray-500">Choose a city, building, floor, room or workstation to see who is there and manage it.</p>
                    </div>
                @endif
            </div>
        </aside>
    </div>
</x-filament-panels::page>
