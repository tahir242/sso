<?php

namespace App\Support;

use Filament\Support\Colors\Color;

/**
 * Brand palette. primary-600 is the official red #B50000.
 *
 * Keep in sync with the `--color-primary-*` tokens in resources/css/app.css
 * (the portal/login screens), so Filament and the portal look identical.
 */
final class Brand
{
    public const array PRIMARY = [
        50 => '#fff1f1',
        100 => '#ffdfdf',
        200 => '#ffc5c5',
        300 => '#ff9d9d',
        400 => '#f25555',
        500 => '#d10f0f',
        600 => '#b50000',
        700 => '#990000',
        800 => '#7e0505',
        900 => '#690b0b',
        950 => '#3a0000',
    ];

    /**
     * Explicit shades for Filament (its generated palettes would shift the
     * brand red to a lighter tone).
     *
     * @return array<int, string>
     */
    public static function filamentPrimary(): array
    {
        return array_map(Color::convertToOklch(...), self::PRIMARY);
    }
}
