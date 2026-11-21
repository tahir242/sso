<?php

namespace App\Domain\Helpdesk\Support;

use App\Domain\Helpdesk\Models\Ticket;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Helpdesk figures for tickets raised since a date. Computed in PHP so it
 * works on every database (no vendor-specific date functions).
 */
final class HelpdeskReport
{
    /**
     * @return array{
     *     raised: int, resolved: int, open_now: int, overdue_now: int,
     *     response_on_time: ?int, resolve_on_time: ?int, avg_response_hours: ?float, median_resolve_hours: ?float,
     *     by_application: Collection<int, array<string, mixed>>, by_assignee: Collection<int, array<string, mixed>>,
     *     by_category: Collection<int, array<string, mixed>>
     * }
     */
    public static function since(CarbonInterface $from): array
    {
        $tickets = Ticket::query()
            ->where('created_at', '>=', $from)
            ->with(['application:id,name', 'assignee:id,name', 'category:id,name'])
            ->get(['id', 'application_id', 'category_id', 'assignee_id', 'status', 'priority', 'created_at',
                'response_due_at', 'first_responded_at', 'due_at', 'resolved_at']);

        $resolved = $tickets->whereNotNull('resolved_at');

        return [
            'raised' => $tickets->count(),
            'resolved' => $resolved->count(),
            'open_now' => Ticket::query()->active()->count(),
            'overdue_now' => Ticket::query()->overdue()->count(),
            'response_on_time' => self::percent(self::responseOutcomes($tickets)),
            'resolve_on_time' => self::percent(self::resolveOutcomes($tickets)),
            'avg_response_hours' => self::round($tickets->whereNotNull('first_responded_at')
                ->avg(fn (Ticket $t) => $t->created_at->diffInMinutes($t->first_responded_at) / 60)),
            'median_resolve_hours' => self::round($resolved
                ->map(fn (Ticket $t) => $t->created_at->diffInMinutes($t->resolved_at) / 60)->median()),
            'by_application' => self::group($tickets, fn (Ticket $t) => $t->application?->name ?? 'General IT'),
            'by_assignee' => self::group($tickets, fn (Ticket $t) => $t->assignee?->name ?? 'Unassigned'),
            'by_category' => self::group($tickets, fn (Ticket $t) => $t->category?->name ?? 'No category'),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private static function group(Collection $tickets, callable $key): Collection
    {
        return $tickets->groupBy($key)
            ->map(fn (Collection $group, string $name) => [
                'name' => $name,
                'raised' => $group->count(),
                'open' => $group->filter(fn (Ticket $t) => $t->status->isActive())->count(),
                'resolved' => $group->whereNotNull('resolved_at')->count(),
                'on_time' => self::percent(self::resolveOutcomes($group)),
            ])
            ->sortByDesc('raised')
            ->values();
    }

    /**
     * true = first reply in time; false = late or overdue without a reply.
     *
     * @return Collection<int, bool>
     */
    private static function responseOutcomes(Collection $tickets): Collection
    {
        return $tickets
            ->filter(fn (Ticket $t) => $t->response_due_at && ($t->first_responded_at || $t->response_due_at->isPast()))
            ->map(fn (Ticket $t) => $t->first_responded_at !== null && $t->first_responded_at->lte($t->response_due_at));
    }

    /**
     * true = resolved by the due date; false = resolved late or still open past it.
     *
     * @return Collection<int, bool>
     */
    private static function resolveOutcomes(Collection $tickets): Collection
    {
        return $tickets
            ->filter(fn (Ticket $t) => $t->due_at && ($t->resolved_at || $t->due_at->isPast()))
            ->map(fn (Ticket $t) => $t->resolved_at !== null && $t->resolved_at->lte($t->due_at));
    }

    private static function percent(Collection $outcomes): ?int
    {
        return $outcomes->isEmpty() ? null : (int) round($outcomes->filter()->count() / $outcomes->count() * 100);
    }

    private static function round(mixed $hours): ?float
    {
        return $hours === null ? null : round((float) $hours, 1);
    }
}
