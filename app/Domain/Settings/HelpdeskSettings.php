<?php

namespace App\Domain\Settings;

use App\Domain\Helpdesk\Enums\TicketPriority;
use Spatie\LaravelSettings\Settings;

/**
 * Helpdesk service levels. Hours are calendar hours (the helpdesk is 24/7).
 */
class HelpdeskSettings extends Settings
{
    /** Time to the first reply from IT, whatever the priority. */
    public int $first_response_hours;

    public int $resolve_hours_urgent;

    public int $resolve_hours_high;

    public int $resolve_hours_normal;

    public int $resolve_hours_low;

    /** Resolved tickets close by themselves after this many days without a reply; 0 = never. */
    public int $auto_close_days;

    public static function group(): string
    {
        return 'helpdesk';
    }

    public function resolveHours(TicketPriority $priority): int
    {
        return match ($priority) {
            TicketPriority::Urgent => $this->resolve_hours_urgent,
            TicketPriority::High => $this->resolve_hours_high,
            TicketPriority::Normal => $this->resolve_hours_normal,
            TicketPriority::Low => $this->resolve_hours_low,
        };
    }
}
