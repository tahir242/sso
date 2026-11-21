<?php

namespace App\Filament\Widgets;

use App\Domain\Audit\Support\IdentityInsights;
use Filament\Widgets\ChartWidget;

class SignInActivityChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Sign-in activity';

    protected ?string $description = 'Successful and failed sign-ins, last 14 days';

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 2];

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['super-admin', 'auditor', 'helpdesk']);
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $daily = IdentityInsights::daily(14);

        return [
            'labels' => $daily['labels'],
            'datasets' => [
                [
                    'label' => 'Successful',
                    'data' => $daily['series']['login.succeeded'],
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.12)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
                [
                    'label' => 'Failed',
                    'data' => $daily['series']['login.failed'],
                    'borderColor' => '#b50000',
                    'backgroundColor' => 'rgba(181, 0, 0, 0.08)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
                [
                    'label' => 'Lockouts',
                    'data' => $daily['series']['login.locked'],
                    'borderColor' => '#f59e0b',
                    'borderDash' => [4, 4],
                    'tension' => 0.35,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
            'interaction' => ['intersect' => false, 'mode' => 'index'],
        ];
    }
}
