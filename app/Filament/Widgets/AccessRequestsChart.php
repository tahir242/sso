<?php

namespace App\Filament\Widgets;

use App\Domain\Audit\Support\IdentityInsights;
use Filament\Widgets\ChartWidget;

class AccessRequestsChart extends ChartWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'Access requests';

    protected ?string $maxHeight = '300px';

    public function getDescription(): ?string
    {
        return array_sum(IdentityInsights::accessRequestsByStatus()) === 0
            ? 'No access requests in the last 90 days'
            : 'Outcomes, last 90 days';
    }

    protected function getType(): string
    {
        return 'pie';
    }

    protected function getData(): array
    {
        $rows = IdentityInsights::accessRequestsByStatus();

        return [
            'labels' => array_keys($rows),
            'datasets' => [[
                'data' => array_values($rows),
                'backgroundColor' => ['#f59e0b', '#10b981', '#b50000', '#a1a1aa'],
                'borderWidth' => 0,
            ]],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }
}
