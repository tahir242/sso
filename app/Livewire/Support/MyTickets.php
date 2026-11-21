<?php

namespace App\Livewire\Support;

use App\Domain\Helpdesk\Enums\TicketStatus;
use App\Domain\Helpdesk\Models\Ticket;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('IT help · My requests')]
class MyTickets extends Component
{
    /** active | closed */
    #[Url(except: 'active')]
    public string $tab = 'active';

    public function render(): View
    {
        $base = Ticket::query()->where('requester_id', auth()->id());

        return view('livewire.support.my-tickets', [
            'tickets' => (clone $base)
                ->when($this->tab === 'active',
                    fn ($q) => $q->whereIn('status', [...TicketStatus::active(), TicketStatus::Resolved]),
                    fn ($q) => $q->where('status', TicketStatus::Closed))
                ->with(['application', 'category', 'assignee'])
                ->orderByDesc('last_activity_at')
                ->limit(100)
                ->get(),
            'waiting' => (clone $base)->where('status', TicketStatus::Waiting)->count(),
        ]);
    }
}
