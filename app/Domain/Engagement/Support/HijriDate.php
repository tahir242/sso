<?php

namespace App\Domain\Engagement\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use IntlCalendar;

/**
 * Gregorian -> Hijri conversion using ICU's Umm al-Qura calendar (ext-intl).
 *
 * Umm al-Qura is an astronomical estimate; actual dates in Pakistan depend on
 * moon sighting, so occasions support a per-year admin override and the
 * `hijri_offset_days` setting (e.g. -1 when Pakistan is typically a day behind).
 */
final class HijriDate
{
    /**
     * @return array{0: int, 1: int, 2: int} [year, month (1-12), day]
     */
    public static function fromGregorian(CarbonInterface $date): array
    {
        $calendar = IntlCalendar::createInstance('UTC', 'en_US@calendar=islamic-umalqura');
        // Noon UTC on the same calendar day avoids any day shift from time zones.
        $noonUtc = CarbonImmutable::create($date->year, $date->month, $date->day, 12, 0, 0, 'UTC');
        $calendar->setTime($noonUtc->getTimestamp() * 1000.0);

        return [
            $calendar->get(IntlCalendar::FIELD_YEAR),
            $calendar->get(IntlCalendar::FIELD_MONTH) + 1,
            $calendar->get(IntlCalendar::FIELD_DAY_OF_MONTH),
        ];
    }
}
