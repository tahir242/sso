<?php

namespace App\Filament\Pages;

use App\Domain\Helpdesk\Support\Helpdesk;
use App\Domain\Helpdesk\Support\HelpdeskReport;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Volumes and service-level performance for the helpdesk.
 */
class HelpdeskReports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Helpdesk';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?string $title = 'Helpdesk reports';

    protected static ?string $slug = 'helpdesk/reports';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.helpdesk.reports';

    #[Url]
    public int $days = 30;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole([...Helpdesk::AGENT_ROLES, 'auditor']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $this->days = in_array($this->days, [7, 30, 90, 365], true) ? $this->days : 30;

        return ['report' => HelpdeskReport::since(now()->subDays($this->days)->startOfDay())];
    }
}
