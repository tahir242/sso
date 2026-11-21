<?php

namespace App\Domain\Requests\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum NewStaffStatus: string implements HasColor, HasLabel
{
    /** Waiting for the head of department. */
    case Pending = 'pending';

    /** HOD confirmed; waiting for the IT helpdesk to create the account. */
    case Approved = 'approved';

    case Rejected = 'rejected';

    /** Account created. */
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for head of department',
            self::Approved => 'Approved — account to be created',
            self::Rejected => 'Rejected',
            self::Completed => 'Account created',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'info',
            self::Rejected => 'danger',
            self::Completed => 'success',
        };
    }
}
