<?php

namespace App\Filament\Pages;

use App\Support\Operations\ReadinessCheck;
use App\Support\Operations\SystemInformation as Information;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Server, PHP, Laravel, database and background-work facts, plus the go-live
 * health checks of `sso:check`, as the web server sees them. Super-admin only.
 */
class SystemInformation extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?string $navigationLabel = 'System information';

    protected static ?string $title = 'System information & health';

    protected static ?string $slug = 'settings/system';

    protected static ?int $navigationSort = 42;

    protected string $view = 'filament.pages.system-information';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('super-admin');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(fn () => null), // re-renders the page, which reads everything again
        ];
    }

    protected function getViewData(): array
    {
        $checks = collect(app(ReadinessCheck::class)->run())
            ->map(fn (array $r) => ['status' => $r[0], 'area' => $r[1], 'message' => $r[2]]);

        return [
            'sections' => app(Information::class)->sections(),
            'checks' => $checks->groupBy('area'),
            'counts' => $checks->countBy('status'),
            'checkedAt' => now()->format('j M Y, H:i:s'),
        ];
    }
}
