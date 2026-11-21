<?php

namespace App\Domain\Helpdesk\Actions;

use App\Domain\Federation\Models\Application;
use App\Domain\Helpdesk\Enums\TicketPriority;
use App\Domain\Helpdesk\Enums\TicketStatus;
use App\Domain\Helpdesk\Models\Ticket;
use App\Domain\Helpdesk\Models\TicketCategory;
use App\Domain\Helpdesk\Notifications\TicketNotification;
use App\Domain\Helpdesk\Support\Helpdesk;
use App\Domain\Identity\Models\User;
use App\Domain\Settings\HelpdeskSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Raises a ticket, sets its SLA due dates and assigns it to the category's
 * default assignee (if any). The assignee, or else the helpdesk, is told.
 */
final readonly class OpenTicket
{
    public function __construct(private HelpdeskSettings $settings) {}

    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    public function handle(User $requester, ?Application $application, ?TicketCategory $category, string $subject, string $description, TicketPriority $priority, array $attachments = []): Ticket
    {
        $ticket = DB::transaction(fn () => Ticket::create([
            'application_id' => $application?->getKey() ?? $category?->application_id,
            'category_id' => $category?->getKey(),
            'requester_id' => $requester->getKey(),
            'assignee_id' => $category?->default_assignee_id,
            'subject' => trim($subject),
            'description' => trim($description),
            'priority' => $priority,
            'status' => TicketStatus::Open,
            'response_due_at' => now()->addHours($this->settings->first_response_hours),
            'due_at' => now()->addHours($this->settings->resolveHours($priority)),
            'last_activity_at' => now(),
        ]));

        foreach ($attachments as $file) {
            $ticket->addMedia($file)->usingName($file->getClientOriginalName())->toMediaCollection('attachments');
        }

        $ticket->load(['assignee', 'application']);

        Notification::send(Helpdesk::watchers($ticket)->reject(fn (User $u) => $u->is($requester)), new TicketNotification(
            __(':ref :priority ticket: :subject', ['ref' => $ticket->reference, 'priority' => $priority->getLabel(), 'subject' => $ticket->subject]),
            __(':name: :app:category', [
                'name' => $requester->name,
                'app' => $ticket->application?->name ?? __('General IT'),
                'category' => $category ? ' · '.$category->name : '',
            ]),
            TicketLinks::staff($ticket),
        ));

        return $ticket;
    }
}
