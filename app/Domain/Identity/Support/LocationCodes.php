<?php

namespace App\Domain\Identity\Support;

use App\Domain\Identity\Enums\LocationType;
use App\Domain\Identity\Models\Location;
use Illuminate\Support\Str;

/**
 * Location coding standard. Codes are assigned automatically, never
 * typed, and read from the outside in:
 *
 *   City         3 letters of the name           KAR
 *   Building     city + B + 2-digit sequence     KAR-B01
 *   Floor        building + F + sequence         KAR-B01-F01
 *   Room         floor + R + sequence            KAR-B01-F01-R01
 *   Workstation  room + W + sequence             KAR-B01-F01-R01-W01
 *
 * Sequences continue from the highest number in use within the parent, so a code
 * stays with its place while it exists. Moving a place re-codes it and
 * everything inside it under the new parent.
 */
final class LocationCodes
{
    public static function prefix(LocationType $type): string
    {
        return match ($type) {
            LocationType::City => '',
            LocationType::Building => 'B',
            LocationType::Floor => 'F',
            LocationType::Room => 'R',
            LocationType::Workstation => 'W',
        };
    }

    public static function next(Location $location, ?Location $parent): string
    {
        if ($location->type === LocationType::City) {
            return self::cityCode($location);
        }

        $stem = $parent->code.'-'.self::prefix($location->type);

        $highest = Location::query()
            ->where('parent_id', $parent->getKey())
            ->where('code', 'like', $stem.'%')
            ->when($location->exists, fn ($q) => $q->whereKeyNot($location->getKey()))
            ->pluck('code')
            ->map(fn (string $code) => (int) substr($code, strlen($stem)))
            ->max() ?? 0;

        return $stem.str_pad((string) ($highest + 1), 2, '0', STR_PAD_LEFT);
    }

    private static function cityCode(Location $location): string
    {
        $letters = Str::upper(preg_replace('/[^A-Za-z]/', '', Str::ascii($location->name)));
        $base = str_pad(substr($letters, 0, 3), 3, 'X');

        $taken = Location::query()->where('type', LocationType::City)
            ->when($location->exists, fn ($q) => $q->whereKeyNot($location->getKey()))
            ->where('code', 'like', $base.'%')->pluck('code')->all();

        if (! in_array($base, $taken, true)) {
            return $base;
        }

        for ($n = 2; ; $n++) {
            if (! in_array($base.$n, $taken, true)) {
                return $base.$n;
            }
        }
    }

    /**
     * Re-codes a place and everything inside it (after a move, or to apply
     * the standard to existing data).
     */
    public static function recode(Location $location): void
    {
        $location->code = self::next($location, $location->parent_id ? Location::find($location->parent_id) : null);
        $location->saveQuietly();

        foreach (Location::query()->where('parent_id', $location->getKey())->orderBy('sort_order')->orderBy('id')->get() as $child) {
            $child->code = '~'.$child->getKey(); // temporary, unique (codes have a unique index)
            $child->saveQuietly();
        }

        foreach (Location::query()->where('parent_id', $location->getKey())->orderBy('sort_order')->orderBy('id')->get() as $child) {
            self::recode($child);
        }
    }
}
