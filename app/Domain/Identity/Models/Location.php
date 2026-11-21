<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\LocationType;
use App\Domain\Identity\Support\LocationCodes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One node of the location dictionary: a city, building, floor, room or
 * workstation. Users are placed at any node (usually a workstation or room).
 */
#[Fillable(['parent_id', 'type', 'name', 'description', 'sort_order', 'active'])]
class Location extends Model
{
    use LogsActivity;

    private const string PATHS_CACHE = 'locations:paths';

    protected function casts(): array
    {
        return [
            'type' => LocationType::class,
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $location): void {
            $parent = $location->parent_id ? self::find($location->parent_id) : null;
            $expected = $location->type->parent();

            if ($expected !== ($parent?->type)) {
                throw new LogicException($expected
                    ? "A {$location->type->value} must be inside a {$expected->value}."
                    : 'A city cannot be inside another location.');
            }
        });

        // Codes follow the location coding standard and are always generated (see LocationCodes).
        static::creating(function (self $location): void {
            $location->code = LocationCodes::next($location, $location->parent_id ? self::find($location->parent_id) : null);
        });

        static::updated(function (self $location): void {
            if ($location->wasChanged('parent_id')) {
                LocationCodes::recode($location);
            }
        });

        // FKs are NO ACTION: never leave users or children pointing at a deleted node.
        static::deleting(function (self $location): void {
            if ($location->children()->exists() || $location->users()->exists()) {
                throw new LogicException('Move or remove what is inside this location first.');
            }
        });

        static::saved(fn () => Cache::forget(self::PATHS_CACHE));
        static::deleted(fn () => Cache::forget(self::PATHS_CACHE));
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * "Capital City › Main Building › 2nd floor › Room 204" for every location,
     * built from one query (cached; cleared on every change).
     *
     * @return array<int, string>
     */
    public static function paths(): array
    {
        return Cache::remember(self::PATHS_CACHE, 3600, function (): array {
            $all = self::query()->get(['id', 'parent_id', 'name'])->keyBy('id');
            $paths = [];

            foreach ($all as $id => $location) {
                $names = [];
                $node = $location;
                $guard = 0;
                while ($node && $guard++ < 10) {
                    array_unshift($names, $node->name);
                    $node = $node->parent_id ? $all->get($node->parent_id) : null;
                }
                $paths[$id] = implode(' › ', $names);
            }

            asort($paths, SORT_NATURAL | SORT_FLAG_CASE);

            return $paths;
        });
    }

    public function path(): string
    {
        return self::paths()[$this->getKey()] ?? $this->name;
    }

    /**
     * Ids of this location and everything inside it.
     *
     * @return list<int>
     */
    public function descendantIds(?Collection $all = null): array
    {
        $all ??= self::query()->get(['id', 'parent_id']);
        $byParent = $all->groupBy('parent_id');
        $ids = [];
        $stack = [$this->getKey()];

        while ($stack) {
            $id = array_pop($stack);
            $ids[] = $id;
            foreach ($byParent->get($id, []) as $child) {
                $stack[] = $child->id;
            }
        }

        return $ids;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['parent_id', 'type', 'name', 'code', 'description', 'sort_order', 'active'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
