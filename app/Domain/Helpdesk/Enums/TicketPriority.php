<?php

namespace App\Domain\Helpdesk\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TicketPriority: string implements HasColor, HasLabel
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    /** Plain-language guidance shown to the person raising the ticket. */
    public function hint(): string
    {
        return match ($this) {
            self::Low => 'A question, or something minor that can wait.',
            self::Normal => 'Something is wrong but I can still work.',
            self::High => 'I can’t do part of my work.',
            self::Urgent => 'Patient care or a whole team is affected.',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Normal => 'info',
            self::High => 'warning',
            self::Urgent => 'danger',
        };
    }
}
