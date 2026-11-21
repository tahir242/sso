<?php

namespace App\Filament\Pages;

use App\Domain\Identity\Enums\LocationType;
use App\Domain\Identity\Models\Location;
use App\Domain\Identity\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use LogicException;
use UnitEnum;

/**
 * Location dictionary: City › Building › Floor › Room › Workstation, with
 * the users placed at each location. Tree on the left, details and actions
 * for the selected place on the right.
 */
class LocationDictionary extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Identity';

    protected static ?string $navigationLabel = 'Locations';

    protected static ?string $title = 'Locations';

    protected static ?string $slug = 'locations';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.locations.dictionary';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'at')]
    public ?int $selectedId = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['super-admin', 'helpdesk', 'auditor']);
    }

    public static function canManage(): bool
    {
        return (bool) auth()->user()?->hasRole('super-admin');
    }

    public function select(int $id): void
    {
        $this->selectedId = $id;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $all = Location::query()->withCount('users')->orderBy('sort_order')->orderBy('name')->get();
        $byParent = $all->groupBy(fn (Location $l) => $l->parent_id ?? 0);

        $selected = $this->selectedId ? $all->firstWhere('id', $this->selectedId) : null;
        if ($this->selectedId && ! $selected) {
            $this->selectedId = null;
        }

        // Search: matching places plus their ancestors (so the tree stays navigable).
        $visible = null;
        $term = Str::lower(trim($this->search));
        if ($term !== '') {
            $byId = $all->keyBy('id');
            $visible = [];
            $userMatches = User::query()->whereNotNull('location_id')
                ->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('username', 'like', "%{$term}%")->orWhere('emp_id', 'like', "%{$term}%"))
                ->pluck('location_id');

            foreach ($all as $location) {
                $hit = str_contains(Str::lower($location->name.' '.$location->code), $term) || $userMatches->contains($location->id);
                for ($node = $hit ? $location : null; $node; $node = $node->parent_id ? $byId->get($node->parent_id) : null) {
                    $visible[$node->id] = true;
                }
            }
        }

        $usersByLocation = User::query()->whereNotNull('location_id')
            ->orderBy('name')->get(['id', 'ulid', 'name', 'username', 'designation', 'location_id', 'status'])
            ->groupBy('location_id');

        $totals = [];
        foreach (LocationType::cases() as $type) {
            $totals[$type->value] = $all->where('type', $type)->count();
        }

        return [
            'roots' => $byParent->get(0, collect()),
            'byParent' => $byParent,
            'visible' => $visible,
            'term' => $term,
            'selected' => $selected,
            'usersByLocation' => $usersByLocation,
            'totals' => $totals,
            'placedUsers' => $usersByLocation->flatten()->count(),
            'unplacedUsers' => User::query()->whereNull('location_id')->count(),
            'details' => $selected ? $this->details($selected, $all) : null,
            'canManage' => self::canManage(),
        ];
    }

    /**
     * @param  Collection<int, Location>  $all
     * @return array<string, mixed>
     */
    private function details(Location $selected, Collection $all): array
    {
        $ids = $selected->descendantIds($all);
        $inside = $all->whereIn('id', $ids)->where('id', '!=', $selected->id);
        $counts = [];
        foreach (LocationType::cases() as $type) {
            if ($n = $inside->where('type', $type)->count()) {
                $counts[$type->plural()] = $n;
            }
        }

        $chain = [];
        for ($node = $selected; $node; $node = $node->parent_id ? $all->firstWhere('id', $node->parent_id) : null) {
            array_unshift($chain, $node);
        }

        return [
            'chain' => $chain,
            'counts' => $counts,
            'usersInside' => User::query()->whereIn('location_id', $ids)->count(),
            'users' => User::query()->where('location_id', $selected->id)->with('department:id,name')->orderBy('name')->limit(100)->get(),
        ];
    }

    // ---- Actions -------------------------------------------------------

    public function createAction(): Action
    {
        return Action::make('create')
            ->label(fn (array $arguments) => 'Add '.($this->childType($arguments)?->value ?? 'city'))
            ->icon(Heroicon::OutlinedPlus)
            ->visible(self::canManage())
            ->modalHeading(fn (array $arguments) => 'Add '.($this->childType($arguments)?->value ?? 'city')
                .(($parent = $this->parentFrom($arguments)) ? ' in '.$parent->name : ''))
            ->modalDescription(fn (array $arguments) => 'The location code is assigned automatically'.(($parent = $this->parentFrom($arguments)) ? ' under '.$parent->code : '').'.')
            ->schema($this->fields())
            ->action(function (array $data, array $arguments): void {
                $parent = $this->parentFrom($arguments);
                $type = $parent ? $parent->type->child() : LocationType::City;

                if (! $type) {
                    Notification::make()->title('Workstations cannot contain other places')->danger()->send();

                    return;
                }

                $location = Location::create([...$data, 'type' => $type, 'parent_id' => $parent?->id]);
                $this->selectedId = $location->id;
                Notification::make()->title($type->getLabel().' added')->body('Code '.$location->code)->success()->send();
            });
    }

    public function editAction(): Action
    {
        return Action::make('edit')
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->visible(self::canManage())
            ->fillForm(fn (array $arguments) => Location::find($arguments['id'] ?? null)?->only(['name', 'description', 'sort_order', 'active']) ?? [])
            ->schema($this->fields())
            ->action(function (array $data, array $arguments): void {
                Location::findOrFail($arguments['id'])->update($data);
                Notification::make()->title('Saved')->success()->send();
            });
    }

    public function moveAction(): Action
    {
        return Action::make('move')
            ->label('Move')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->visible(self::canManage())
            ->modalDescription('Moves this place — with everything and everyone inside it — under another parent of the same level.')
            ->schema(fn (array $arguments) => [
                Select::make('parent_id')->label('New parent')->required()->searchable()
                    ->options(function () use ($arguments): array {
                        $location = Location::find($arguments['id'] ?? null);
                        $parentType = $location?->type->parent();

                        if (! $parentType) {
                            return [];
                        }

                        $ids = Location::query()->where('type', $parentType)->where('id', '!=', $location->parent_id)->pluck('id');

                        return collect(Location::paths())->only($ids)->all();
                    }),
            ])
            ->visible(fn (array $arguments) => self::canManage())
            ->action(function (array $data, array $arguments): void {
                $location = Location::findOrFail($arguments['id']);
                $location->update(['parent_id' => (int) $data['parent_id']]);
                Notification::make()->title('Moved')->body('New code '.$location->fresh()->code.' (and everything inside it re-coded)')->success()->send();
            });
    }

    public function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(self::canManage())
            ->requiresConfirmation()
            ->modalDescription('Only empty places can be deleted: move or remove the places and people inside it first.')
            ->action(function (array $arguments): void {
                $location = Location::findOrFail($arguments['id']);

                try {
                    $location->delete();
                } catch (LogicException $e) {
                    Notification::make()->title('Cannot delete '.$location->name)->body($e->getMessage())->danger()->send();

                    return;
                }

                $this->selectedId = $location->parent_id;
                Notification::make()->title('Deleted')->success()->send();
            });
    }

    public function assignUsersAction(): Action
    {
        return Action::make('assignUsers')
            ->label('Place users here')
            ->icon(Heroicon::OutlinedUserPlus)
            ->visible(self::canManage())
            ->modalDescription('People already placed elsewhere are moved here.')
            ->schema([
                Select::make('users')->label('Users')->multiple()->required()->searchable()
                    ->getSearchResultsUsing(fn (string $search) => User::query()
                        ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%")->orWhere('emp_id', 'like', "%{$search}%"))
                        ->orderBy('name')->limit(30)->get()
                        ->mapWithKeys(fn (User $u) => [$u->id => "{$u->name} ({$u->username})"])->all())
                    ->getOptionLabelsUsing(fn (array $values) => User::whereKey($values)->get()->mapWithKeys(fn (User $u) => [$u->id => "{$u->name} ({$u->username})"])->all()),
            ])
            ->action(function (array $data, array $arguments): void {
                $location = Location::findOrFail($arguments['id']);
                User::query()->whereKey($data['users'])->get()->each->update(['location_id' => $location->id]);
                Notification::make()->title(count($data['users']).' '.Str::plural('user', count($data['users'])).' placed in '.$location->name)->success()->send();
            });
    }

    public function removeUserAction(): Action
    {
        return Action::make('removeUser')
            ->label('Remove from location')
            ->icon(Heroicon::OutlinedXMark)
            ->color('gray')
            ->iconButton()
            ->visible(self::canManage())
            ->requiresConfirmation()
            ->modalHeading('Remove this person from the location?')
            ->action(function (array $arguments): void {
                User::query()->whereKey($arguments['user'] ?? 0)->first()?->update(['location_id' => null]);
                Notification::make()->title('Removed from location')->success()->send();
            });
    }

    /**
     * @return array<int, mixed>
     */
    private function fields(): array
    {
        return [
            TextInput::make('name')->required()->maxLength(120)->autofocus(),
            TextInput::make('description')->maxLength(255),
            TextInput::make('sort_order')->label('Order')->numeric()->minValue(0)->default(0),
            Toggle::make('active')->default(true),
        ];
    }

    private function parentFrom(array $arguments): ?Location
    {
        return isset($arguments['parent']) ? Location::find($arguments['parent']) : null;
    }

    private function childType(array $arguments): ?LocationType
    {
        $parent = $this->parentFrom($arguments);

        return $parent ? $parent->type->child() : LocationType::City;
    }
}
