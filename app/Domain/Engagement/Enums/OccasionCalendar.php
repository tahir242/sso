<?php

namespace App\Domain\Engagement\Enums;

use Filament\Support\Contracts\HasLabel;

enum OccasionCalendar: string implements HasLabel
{
    case Gregorian = 'gregorian';
    case Hijri = 'hijri';

    private const array HIJRI_MONTHS = [
        1 => 'Muharram', 2 => 'Safar', 3 => 'Rabi ul Awwal', 4 => 'Rabi ul Thani',
        5 => 'Jumada al-Ula', 6 => 'Jumada al-Thani', 7 => 'Rajab', 8 => 'Shaban',
        9 => 'Ramadan', 10 => 'Shawwal', 11 => 'Dhul Qadah', 12 => 'Dhul Hijjah',
    ];

    public function getLabel(): string
    {
        return match ($this) {
            self::Gregorian => 'Gregorian (fixed date)',
            self::Hijri => 'Hijri (Islamic calendar)',
        };
    }

    /**
     * @return array<int, string>
     */
    public function months(): array
    {
        return match ($this) {
            self::Gregorian => collect(range(1, 12))
                ->mapWithKeys(fn (int $m) => [$m => date('F', mktime(0, 0, 0, $m, 1))])
                ->all(),
            self::Hijri => self::HIJRI_MONTHS,
        };
    }

    public function maxDay(): int
    {
        return $this === self::Hijri ? 30 : 31;
    }
}
