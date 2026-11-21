<?php

namespace App\Filament\Widgets;

use App\Domain\Audit\Support\IdentityInsights;
use App\Filament\Resources\AccessRequests\AccessRequestResource;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\AuthEvents\AuthEventResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Filament\Resources\Users\UserResource;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Headline KPIs: people, protection, applications, activity and workload.
 */
class IdentityOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $t = IdentityInsights::totals();
        $daily = IdentityInsights::daily(14);
        $canSee = fn (string $resource) => $resource::canAccess();
        $url = fn (string $resource, array $params = []) => $resource::canAccess() ? $resource::getUrl('index', $params) : null;

        return [
            Stat::make('Active accounts', number_format($t['active_users']))
                ->description("{$t['active_30d']} signed in this month · {$t['new_30d']} new")
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->icon(Heroicon::OutlinedUserGroup)
                ->color('primary')
                ->url($url(UserResource::class)),

            Stat::make('Two-step verification', $t['mfa_rate'] === null ? '—' : $t['mfa_rate'].'%')
                ->description("{$t['mfa_users']} of {$t['active_users']} active accounts protected")
                ->descriptionIcon(Heroicon::OutlinedShieldCheck)
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color(($t['mfa_rate'] ?? 0) >= 80 ? 'success' : (($t['mfa_rate'] ?? 0) >= 40 ? 'warning' : 'danger')),

            Stat::make('Connected applications', number_format($t['apps']))
                ->description("{$t['assignments']} access grants · {$t['apps_everyone']} open to everyone")
                ->descriptionIcon(Heroicon::OutlinedSquares2x2)
                ->icon(Heroicon::OutlinedSquares2x2)
                ->color('info')
                ->url($url(ApplicationResource::class)),

            Stat::make('Sign-ins (24 h)', number_format($t['signins_24h']))
                ->description('Last 14 days')
                ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp)
                ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
                ->chart($daily['series']['login.succeeded'])
                ->color('success')
                ->url($url(AuthEventResource::class)),

            Stat::make('Failed sign-ins (24 h)', number_format($t['failed_24h']))
                ->description($t['lockouts_24h'] ? "{$t['lockouts_24h']} lockouts" : 'No lockouts')
                ->descriptionIcon($t['lockouts_24h'] ? Heroicon::OutlinedLockClosed : Heroicon::OutlinedCheckCircle)
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->chart($daily['series']['login.failed'])
                ->color($t['failed_24h'] > 20 ? 'danger' : ($t['failed_24h'] > 0 ? 'warning' : 'success'))
                ->url($url(AuthEventResource::class)),

            Stat::make('Pending access requests', number_format($t['pending_requests']))
                ->description($t['pending_requests'] ? 'Waiting for approval' : 'Nothing waiting')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->icon(Heroicon::OutlinedKey)
                ->color($t['pending_requests'] ? 'warning' : 'success')
                ->url($url(AccessRequestResource::class)),

            Stat::make('Open helpdesk tickets', number_format($t['open_tickets']))
                ->description($t['overdue_tickets'] ? "{$t['overdue_tickets']} overdue" : 'All within target')
                ->descriptionIcon($t['overdue_tickets'] ? Heroicon::OutlinedFire : Heroicon::OutlinedCheckCircle)
                ->icon(Heroicon::OutlinedLifebuoy)
                ->color($t['overdue_tickets'] ? 'danger' : 'success')
                ->url($url(TicketResource::class)),

            Stat::make('Accounts missing email', number_format($t['missing_email']))
                ->description($t['missing_email'] ? 'Asked to add one at next sign-in' : 'Every account has an email')
                ->descriptionIcon(Heroicon::OutlinedEnvelope)
                ->icon(Heroicon::OutlinedEnvelope)
                ->color($t['missing_email'] ? 'warning' : 'success')
                ->url($canSee(UserResource::class) ? UserResource::getUrl('index', ['filters' => ['email' => ['value' => '0']]]) : null),
        ];
    }
}
