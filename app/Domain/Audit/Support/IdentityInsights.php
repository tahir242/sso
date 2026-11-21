<?php

namespace App\Domain\Audit\Support;

use App\Domain\Access\Enums\AccessRequestStatus;
use App\Domain\Access\Models\AccessRequest;
use App\Domain\Audit\Enums\AuthEventType;
use App\Domain\Audit\Models\AuthEvent;
use App\Domain\Federation\Enums\AccessMode;
use App\Domain\Federation\Models\Application;
use App\Domain\Federation\Models\ApplicationAssignment;
use App\Domain\Helpdesk\Models\Ticket;
use App\Domain\Identity\Enums\AuthSource;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Figures for the admin dashboard. Plain Eloquent counts and PHP grouping
 * only (no vendor-specific date SQL), so it runs on SQL Server 2014 as well
 * as MySQL/PostgreSQL/SQLite. Results are cached for a minute.
 */
final class IdentityInsights
{
    private const int TTL = 60;

    /**
     * @return array<string, int|float|null>
     */
    public static function totals(): array
    {
        return Cache::remember('insights:totals', self::TTL, function (): array {
            $users = User::query();
            $active = (clone $users)->where('status', UserStatus::Active)->count();
            $withMfa = (clone $users)->where('status', UserStatus::Active)->whereNotNull('two_factor_confirmed_at')->count();
            $since = now()->subDay();

            return [
                'users' => (clone $users)->count(),
                'active_users' => $active,
                'locked_users' => (clone $users)->where('status', UserStatus::Locked)->count(),
                'disabled_users' => (clone $users)->where('status', UserStatus::Disabled)->count(),
                'ad_users' => (clone $users)->where('auth_source', AuthSource::ActiveDirectory)->count(),
                'mfa_users' => $withMfa,
                'mfa_rate' => $active ? round($withMfa / $active * 100) : null,
                'missing_email' => (clone $users)->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''))->count(),
                'active_30d' => (clone $users)->where('last_login_at', '>=', now()->subDays(30))->count(),
                'new_30d' => (clone $users)->where('created_at', '>=', now()->subDays(30))->count(),
                'apps' => Application::query()->active()->count(),
                'apps_everyone' => Application::query()->active()->where('access_mode', AccessMode::Everyone)->count(),
                'assignments' => ApplicationAssignment::query()->current()->count(),
                'signins_24h' => AuthEvent::query()->where('event', AuthEventType::LoginSucceeded)->where('created_at', '>=', $since)->count(),
                'failed_24h' => AuthEvent::query()->where('event', AuthEventType::LoginFailed)->where('created_at', '>=', $since)->count(),
                'lockouts_24h' => AuthEvent::query()->where('event', AuthEventType::LoginLocked)->where('created_at', '>=', $since)->count(),
                'pending_requests' => AccessRequest::query()->where('status', AccessRequestStatus::Pending)->count(),
                'open_tickets' => Ticket::query()->active()->count(),
                'overdue_tickets' => Ticket::query()->overdue()->count(),
            ];
        });
    }

    /**
     * Daily counts of these sign-in events for the last N days (oldest first).
     *
     * @return array{labels: list<string>, series: array<string, list<int>>}
     */
    public static function daily(int $days = 14): array
    {
        return Cache::remember("insights:daily:{$days}", self::TTL, function () use ($days): array {
            $start = CarbonImmutable::today()->subDays($days - 1);
            $types = [AuthEventType::LoginSucceeded, AuthEventType::LoginFailed, AuthEventType::LoginLocked];

            $rows = AuthEvent::query()->where('created_at', '>=', $start)->whereIn('event', $types)
                ->get(['event', 'created_at']);

            $counts = $rows->countBy(fn ($r) => $r->event->value.'|'.$r->created_at->toDateString());

            $labels = [];
            $series = array_fill_keys(array_map(fn (AuthEventType $t) => $t->value, $types), []);
            for ($i = 0; $i < $days; $i++) {
                $day = $start->addDays($i);
                $labels[] = $day->format('j M');
                foreach ($types as $type) {
                    $series[$type->value][] = $counts->get($type->value.'|'.$day->toDateString(), 0);
                }
            }

            return ['labels' => $labels, 'series' => $series];
        });
    }

    /**
     * Applications ranked by people with live access.
     *
     * @return array<string, int>
     */
    public static function applicationsByUsers(int $limit = 8): array
    {
        return Cache::remember("insights:apps:{$limit}", self::TTL, fn (): array => Application::query()->active()
            ->withCount(['assignments' => fn ($q) => $q->current()])
            ->get()
            ->sortByDesc('assignments_count')
            ->take($limit)
            ->mapWithKeys(fn (Application $a) => [$a->name => $a->assignments_count])
            ->all());
    }

    /**
     * Access requests of the last 90 days by status.
     *
     * @return array<string, int>
     */
    public static function accessRequestsByStatus(): array
    {
        return Cache::remember('insights:requests', self::TTL, function (): array {
            $rows = AccessRequest::query()->where('created_at', '>=', now()->subDays(90))->get(['status']);

            return collect(AccessRequestStatus::cases())
                ->mapWithKeys(fn (AccessRequestStatus $s) => [ucfirst($s->value) => $rows->where('status', $s)->count()])
                ->all();
        });
    }
}
