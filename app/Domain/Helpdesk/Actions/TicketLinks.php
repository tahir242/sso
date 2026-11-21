<?php

namespace App\Domain\Helpdesk\Actions;

use App\Domain\Helpdesk\Models\Ticket;

/**
 * Where a notification about a ticket should take each audience.
 */
final class TicketLinks
{
    public static function requester(Ticket $ticket): string
    {
        return route('support.show', $ticket->ulid);
    }

    public static function staff(Ticket $ticket): string
    {
        return url('/admin/tickets/'.$ticket->ulid);
    }
}
