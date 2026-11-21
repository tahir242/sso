<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Domain\Helpdesk\Enums\TicketStatus;
use App\Filament\Resources\Tickets\TicketResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    public function getTabs(): array
    {
        $base = fn () => TicketResource::getEloquentQuery();

        return [
            'mine' => Tab::make('Assigned to me')
                ->modifyQueryUsing(fn (Builder $query) => $query->active()->where('assignee_id', auth()->id()))
                ->badge(fn () => $base()->active()->where('assignee_id', auth()->id())->count() ?: null),
            'unassigned' => Tab::make('Unassigned')
                ->modifyQueryUsing(fn (Builder $query) => $query->active()->whereNull('assignee_id'))
                ->badge(fn () => $base()->active()->whereNull('assignee_id')->count() ?: null)
                ->badgeColor('warning'),
            'overdue' => Tab::make('Overdue')
                ->modifyQueryUsing(fn (Builder $query) => $query->overdue())
                ->badge(fn () => $base()->overdue()->count() ?: null)
                ->badgeColor('danger'),
            'active' => Tab::make('All open')
                ->modifyQueryUsing(fn (Builder $query) => $query->active()),
            'resolved' => Tab::make('Resolved')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', TicketStatus::Resolved)),
            'all' => Tab::make('All'),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        $mine = TicketResource::getEloquentQuery()->active()->where('assignee_id', auth()->id())->exists();

        return $mine ? 'mine' : 'unassigned';
    }
}
