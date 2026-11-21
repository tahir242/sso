<?php

namespace App\Domain\Identity\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Levels of the location dictionary, outermost first. Each level may only
 * sit directly under the level before it (a room under a floor, ...).
 */
enum LocationType: string implements HasIcon, HasLabel
{
    case City = 'city';
    case Building = 'building';
    case Floor = 'floor';
    case Room = 'room';
    case Workstation = 'workstation';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function plural(): string
    {
        return $this === self::City ? 'Cities' : $this->getLabel().'s';
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::City => Heroicon::OutlinedMapPin,
            self::Building => Heroicon::OutlinedBuildingOffice2,
            self::Floor => Heroicon::OutlinedSquare3Stack3d,
            self::Room => Heroicon::OutlinedHomeModern,
            self::Workstation => Heroicon::OutlinedComputerDesktop,
        };
    }

    /** Blade icon component name (for the tree view). */
    public function icon(): string
    {
        return match ($this) {
            self::City => 'heroicon-o-map-pin',
            self::Building => 'heroicon-o-building-office-2',
            self::Floor => 'heroicon-o-square-3-stack-3d',
            self::Room => 'heroicon-o-home-modern',
            self::Workstation => 'heroicon-o-computer-desktop',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::City => 'text-rose-600 bg-rose-50 ring-rose-200 dark:bg-rose-950/50 dark:text-rose-300 dark:ring-rose-900',
            self::Building => 'text-amber-600 bg-amber-50 ring-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:ring-amber-900',
            self::Floor => 'text-sky-600 bg-sky-50 ring-sky-200 dark:bg-sky-950/50 dark:text-sky-300 dark:ring-sky-900',
            self::Room => 'text-emerald-600 bg-emerald-50 ring-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:ring-emerald-900',
            self::Workstation => 'text-violet-600 bg-violet-50 ring-violet-200 dark:bg-violet-950/50 dark:text-violet-300 dark:ring-violet-900',
        };
    }

    /** The level that may be created inside this one. */
    public function child(): ?self
    {
        return match ($this) {
            self::City => self::Building,
            self::Building => self::Floor,
            self::Floor => self::Room,
            self::Room => self::Workstation,
            self::Workstation => null,
        };
    }

    public function parent(): ?self
    {
        return match ($this) {
            self::City => null,
            self::Building => self::City,
            self::Floor => self::Building,
            self::Room => self::Floor,
            self::Workstation => self::Room,
        };
    }
}
