<?php

namespace App\Domain\Engagement\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Visual treatment of the greeting card on the login screen.
 */
enum OccasionTheme: string implements HasLabel
{
    case General = 'general';
    case National = 'national';
    case Eid = 'eid';
    case Ramadan = 'ramadan';
    case NewYear = 'new-year';

    public function getLabel(): string
    {
        return match ($this) {
            self::General => 'General',
            self::National => 'National day (green & white)',
            self::Eid => 'Eid (crescent & stars)',
            self::Ramadan => 'Ramadan (crescent & stars)',
            self::NewYear => 'New year (sparkles)',
        };
    }
}
