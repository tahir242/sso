<?php

namespace App\Domain\Helpdesk\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TicketStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case InProgress = 'in_progress';
    /** Waiting for the requester to answer a question. The SLA clock keeps running. */
    case Waiting = 'waiting';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In progress',
            self::Waiting => 'Waiting for you',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    /** Label from the helpdesk's point of view. */
    public function staffLabel(): string
    {
        return $this === self::Waiting ? 'Waiting on requester' : $this->getLabel();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::InProgress => 'info',
            self::Waiting => 'gray',
            self::Resolved => 'success',
            self::Closed => 'gray',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Open, self::InProgress, self::Waiting], true);
    }

    /**
     * @return array<int, self>
     */
    public static function active(): array
    {
        return [self::Open, self::InProgress, self::Waiting];
    }
}
