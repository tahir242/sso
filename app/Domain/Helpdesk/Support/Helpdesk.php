<?php

namespace App\Domain\Helpdesk\Support;

use App\Domain\Helpdesk\Models\Ticket;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Who may see and handle tickets.
 *
 * - Requester: sees their own ticket (never internal notes), replies, closes, reopens.
 * - Helpdesk staff (helpdesk, super-admin): every ticket.
 * - Application owner and the assignee: that ticket.
 */
final class Helpdesk
{
    public const AGENT_ROLES = ['helpdesk', 'super-admin'];

    public static function canHandle(Ticket $ticket, User $user): bool
    {
        return $user->hasAnyRole(self::AGENT_ROLES)
            || $ticket->assignee_id === $user->getKey()
            || ($ticket->application && $ticket->application->owner_user_id === $user->getKey());
    }

    public static function canView(Ticket $ticket, User $user): bool
    {
        return $ticket->requester_id === $user->getKey() || self::canHandle($ticket, $user);
    }

    /**
     * Helpdesk staff (falls back to super-admins when nobody has the helpdesk role).
     *
     * @return Collection<int, User>
     */
    public static function agents(): Collection
    {
        $roles = Role::whereIn('name', self::AGENT_ROLES)->pluck('name')->all();
        if ($roles === []) {
            return collect();
        }

        $staff = User::role($roles)->with('roles')->get()->filter(fn (User $u) => $u->status->canSignIn());
        $helpdesk = $staff->filter(fn (User $u) => $u->hasRole('helpdesk'));

        return ($helpdesk->isNotEmpty() ? $helpdesk : $staff)->values();
    }

    /**
     * People a ticket can be assigned to: helpdesk staff plus the application owner.
     *
     * @return Collection<int, User>
     */
    public static function assignableFor(?Ticket $ticket): Collection
    {
        $roles = Role::whereIn('name', self::AGENT_ROLES)->pluck('name')->all();
        $people = $roles ? User::role($roles)->get() : collect();

        if ($ticket?->application?->owner) {
            $people->push($ticket->application->owner);
        }

        return $people->filter(fn (User $u) => $u->status->canSignIn())->unique('id')->sortBy('name')->values();
    }

    /**
     * Who hears about activity on a ticket: the assignee, else the helpdesk.
     *
     * @return Collection<int, User>
     */
    public static function watchers(Ticket $ticket): Collection
    {
        return $ticket->assignee ? collect([$ticket->assignee]) : self::agents();
    }
}
