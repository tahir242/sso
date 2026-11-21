<?php

namespace Database\Seeders;

use App\Domain\Engagement\Enums\OccasionCalendar;
use App\Domain\Engagement\Enums\OccasionTheme;
use App\Domain\Engagement\Models\Occasion;
use Illuminate\Database\Seeder;

class OccasionSeeder extends Seeder
{
    public function run(): void
    {
        $occasions = [
            // Gregorian, fixed every year
            ['New Year', 'Wishing the entire team a healthy and happy New Year.', null, OccasionCalendar::Gregorian, 1, 1, 1, OccasionTheme::NewYear, 10],
            ['Pakistan Day', 'Pakistan Day Mubarak! Remembering the resolution that shaped our nation.', 'یومِ پاکستان مبارک', OccasionCalendar::Gregorian, 3, 23, 1, OccasionTheme::National, 20],
            ['Independence Day', 'Jashn-e-Azadi Mubarak! Happy 14th August to everyone.', 'جشنِ آزادی مبارک', OccasionCalendar::Gregorian, 8, 14, 1, OccasionTheme::National, 30],
            ['Iqbal Day', 'Remembering Allama Muhammad Iqbal, the poet of the East.', 'یومِ اقبال', OccasionCalendar::Gregorian, 11, 9, 1, OccasionTheme::National, 20],
            ['Quaid-e-Azam Day', 'Honouring Quaid-e-Azam Muhammad Ali Jinnah — Unity, Faith, Discipline.', 'یومِ قائد', OccasionCalendar::Gregorian, 12, 25, 1, OccasionTheme::National, 20],

            // Hijri (Umm al-Qura estimate; admins can override for moon sighting)
            ['Ramadan Kareem', 'Ramadan Kareem! May this blessed month bring peace, health and mercy.', 'رمضان کریم', OccasionCalendar::Hijri, 9, 1, 29, OccasionTheme::Ramadan, 40],
            ['Eid ul Fitr', 'Eid Mubarak! Wishing you and your family joy, health and blessings.', 'عید مبارک', OccasionCalendar::Hijri, 10, 1, 3, OccasionTheme::Eid, 50],
            ['Eid ul Adha', 'Eid ul Adha Mubarak! May your sacrifices be accepted.', 'عید الاضحیٰ مبارک', OccasionCalendar::Hijri, 12, 10, 3, OccasionTheme::Eid, 50],
            ['Eid Milad-un-Nabi', 'Eid Milad-un-Nabi ﷺ Mubarak.', 'عید میلاد النبی ﷺ مبارک', OccasionCalendar::Hijri, 3, 12, 1, OccasionTheme::Eid, 45],
        ];

        foreach ($occasions as [$title, $message, $messageUr, $calendar, $month, $day, $duration, $theme, $priority]) {
            Occasion::updateOrCreate(
                ['title' => $title],
                [
                    'message' => $message,
                    'message_ur' => $messageUr,
                    'calendar' => $calendar,
                    'month' => $month,
                    'day' => $day,
                    'duration_days' => $duration,
                    'theme' => $theme,
                    'priority' => $priority,
                    'active' => true,
                ],
            );
        }
    }
}
