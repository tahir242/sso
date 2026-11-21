<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->reference;
    }

    protected function getHeaderActions(): array
    {
        return [
            TicketResource::replyAction(),
            TicketResource::takeAction(),
            TicketResource::detailsAction(),
            TicketResource::statusAction(),
        ];
    }
}
