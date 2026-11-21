<?php

namespace App\Domain\Engagement\Models;

use App\Domain\Engagement\Enums\OccasionCalendar;
use App\Domain\Engagement\Enums\OccasionTheme;
use App\Domain\Engagement\Services\LoginGreeting;
use App\Domain\Engagement\Support\HijriDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'title', 'message', 'message_ur', 'calendar', 'month', 'day', 'duration_days',
    'override_starts_on', 'override_ends_on', 'theme', 'priority', 'active',
])]
class Occasion extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'calendar' => OccasionCalendar::class,
            'theme' => OccasionTheme::class,
            'month' => 'integer',
            'day' => 'integer',
            'duration_days' => 'integer',
            'priority' => 'integer',
            'active' => 'boolean',
            'override_starts_on' => 'immutable_date',
            'override_ends_on' => 'immutable_date',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => LoginGreeting::forget());
        static::deleted(fn () => LoginGreeting::forget());
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /**
     * Whether the greeting should be shown on the given (local) date.
     */
    public function isCelebratedOn(CarbonImmutable $date, int $hijriOffsetDays = 0): bool
    {
        $date = $date->startOfDay();

        // An admin override (moon-sighting correction) wins for its own year.
        if ($this->override_starts_on && $this->override_starts_on->year === $date->year) {
            $end = $this->override_ends_on ?? $this->override_starts_on;

            return $date->betweenIncluded($this->override_starts_on, $end);
        }

        $span = max(1, $this->duration_days);

        // The date is inside the occasion if one of the previous `$span` days
        // (including today) is the occasion's first day.
        for ($k = 0; $k < $span; $k++) {
            $candidate = $date->subDays($k);

            if ($this->calendar === OccasionCalendar::Hijri) {
                [, $month, $day] = HijriDate::fromGregorian($candidate->addDays($hijriOffsetDays));
            } else {
                [$month, $day] = [$candidate->month, $candidate->day];
            }

            if ($month === $this->month && $day === $this->day) {
                return true;
            }
        }

        return false;
    }

    /**
     * First Gregorian day of the next (or current) occurrence, for admin display.
     */
    public function nextStartDate(CarbonImmutable $from, int $hijriOffsetDays = 0): ?CarbonImmutable
    {
        $from = $from->startOfDay();

        for ($i = 0; $i <= 400; $i++) {
            $candidate = $from->addDays($i);

            if ($this->calendar === OccasionCalendar::Hijri) {
                [, $month, $day] = HijriDate::fromGregorian($candidate->addDays($hijriOffsetDays));
            } else {
                [$month, $day] = [$candidate->month, $candidate->day];
            }

            if ($month === $this->month && $day === $this->day) {
                return $candidate;
            }
        }

        return null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
