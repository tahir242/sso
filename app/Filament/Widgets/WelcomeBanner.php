<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * Greeting with the signed-in administrator's roles and quick links.
 */
class WelcomeBanner extends Widget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.welcome-banner';
}
