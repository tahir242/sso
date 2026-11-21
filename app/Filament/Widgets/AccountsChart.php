<?php

namespace App\Filament\Widgets;

use App\Domain\Audit\Support\IdentityInsights;
use Filament\Widgets\ChartWidget;

class AccountsChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Accounts';

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['super-admin', 'auditor', 'helpdesk']);
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    public function getDescription(): ?string
    {
        $t = IdentityInsights::totals();
        $local = max(0, $t['users'] - $t['ad_users']);

        return "By status · {$t['ad_users']} Windows (AD) and {$local} local accounts";
    }

    protected function getData(): array
    {
        $t = IdentityInsights::totals();

        return [
            'labels' => ['Active', 'Locked', 'Disabled'],
            'datasets' => [[
                'label' => 'Accounts',
                'data' => [$t['active_users'], $t['locked_users'], $t['disabled_users']],
                'backgroundColor' => ['#10b981', '#f59e0b', '#a1a1aa'],
                'borderWidth' => 0,
            ]],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '55%',
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }
}
