<?php

namespace App\Filament\Widgets;

use App\Domain\Audit\Support\IdentityInsights;
use Filament\Widgets\ChartWidget;

class ApplicationUsageChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Most-used applications';

    protected ?string $description = 'People with access, top 8';

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 2];

    protected ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $apps = IdentityInsights::applicationsByUsers(8);

        return [
            'labels' => array_keys($apps),
            'datasets' => [[
                'label' => 'People with access',
                'data' => array_values($apps),
                'backgroundColor' => '#b50000',
                'borderRadius' => 6,
                'maxBarThickness' => 22,
            ]],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
