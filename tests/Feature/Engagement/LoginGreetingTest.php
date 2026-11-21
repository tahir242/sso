<?php

use App\Domain\Engagement\Enums\OccasionCalendar;
use App\Domain\Engagement\Enums\OccasionTheme;
use App\Domain\Engagement\Models\Occasion;
use App\Domain\Engagement\Models\Quote;
use App\Domain\Engagement\Services\LoginGreeting;
use App\Domain\Settings\GeneralSettings;
use Carbon\CarbonImmutable;

function greetingOn(string $date): ?array
{
    return app(LoginGreeting::class)->forDate(CarbonImmutable::parse($date, 'Asia/Karachi'));
}

function occasion(array $attributes = []): Occasion
{
    return Occasion::create([
        'title' => 'Independence Day',
        'message' => 'Jashn-e-Azadi Mubarak!',
        'calendar' => OccasionCalendar::Gregorian,
        'month' => 8,
        'day' => 14,
        'duration_days' => 1,
        'theme' => OccasionTheme::National,
        'priority' => 10,
        'active' => true,
        ...$attributes,
    ]);
}

it('shows a fixed Gregorian occasion only on its day', function () {
    occasion();
    Quote::create(['text' => 'Unity, Faith, Discipline.', 'author' => 'Quaid-e-Azam']);

    expect(greetingOn('2026-08-14'))->type->toBe('occasion')
        ->and(greetingOn('2026-08-14')['occasion']->title)->toBe('Independence Day')
        ->and(greetingOn('2026-08-15'))->type->toBe('quote');
});

it('spans multi-day occasions across a year boundary', function () {
    occasion(['title' => 'Holidays', 'month' => 12, 'day' => 31, 'duration_days' => 3]);

    expect(greetingOn('2026-12-31'))->type->toBe('occasion')
        ->and(greetingOn('2027-01-02'))->type->toBe('occasion')
        ->and(greetingOn('2027-01-03'))->toBeNull();
});

it('calculates Eid ul Fitr from the Hijri calendar', function () {
    occasion(['title' => 'Eid ul Fitr', 'calendar' => OccasionCalendar::Hijri, 'month' => 10, 'day' => 1, 'duration_days' => 3, 'theme' => OccasionTheme::Eid]);

    // 1 Shawwal 1447 (Umm al-Qura) = 20 March 2026
    expect(greetingOn('2026-03-19'))->toBeNull()
        ->and(greetingOn('2026-03-20'))->type->toBe('occasion')
        ->and(greetingOn('2026-03-22'))->type->toBe('occasion')
        ->and(greetingOn('2026-03-23'))->toBeNull();
});

it('applies the Hijri offset setting for local moon sighting', function () {
    $settings = app(GeneralSettings::class);
    $settings->hijri_offset_days = -1;
    $settings->save();

    occasion(['title' => 'Eid ul Fitr', 'calendar' => OccasionCalendar::Hijri, 'month' => 10, 'day' => 1, 'theme' => OccasionTheme::Eid]);

    expect(greetingOn('2026-03-20'))->toBeNull()
        ->and(greetingOn('2026-03-21'))->type->toBe('occasion');
});

it('uses the admin override dates for that year instead of the calculation', function () {
    occasion([
        'title' => 'Eid ul Fitr', 'calendar' => OccasionCalendar::Hijri, 'month' => 10, 'day' => 1, 'duration_days' => 3,
        'override_starts_on' => '2026-03-21', 'override_ends_on' => '2026-03-23',
    ]);

    expect(greetingOn('2026-03-20'))->toBeNull()
        ->and(greetingOn('2026-03-23'))->type->toBe('occasion');
});

it('prefers the higher-priority occasion when two overlap', function () {
    occasion(['title' => 'Low', 'priority' => 1]);
    occasion(['title' => 'High', 'priority' => 99]);

    expect(greetingOn('2026-08-14')['occasion']->title)->toBe('High');
});

it('ignores inactive occasions and quotes', function () {
    occasion(['active' => false]);
    Quote::create(['text' => 'Hidden', 'active' => false]);

    expect(greetingOn('2026-08-14'))->toBeNull();
});

it('shows a different quote on every visit', function () {
    foreach (range(1, 3) as $i) {
        Quote::create(['text' => "Quote {$i}"]);
    }

    request()->setLaravelSession(app('session.store')); // as on the login page
    $seen = [];
    $previous = null;
    foreach (range(1, 30) as $visit) {
        $id = greetingOn('2026-11-12 08:00')['quote']->id;
        expect($id)->not->toBe($previous); // never the same twice in a row
        $seen[$id] = true;
        $previous = $id;
    }

    expect($seen)->toHaveCount(3);
});

it('keeps showing the only quote when there is just one', function () {
    Quote::create(['text' => 'Only one']);

    expect(greetingOn('2026-11-12')['quote']->text)->toBe('Only one')
        ->and(greetingOn('2026-11-12')['quote']->text)->toBe('Only one');
});

it('can be switched off in settings', function () {
    occasion();
    $settings = app(GeneralSettings::class);
    $settings->login_greetings_enabled = false;
    $settings->save();

    expect(greetingOn('2026-08-14'))->toBeNull();
});
