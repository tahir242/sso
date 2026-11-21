<?php

namespace App\Domain\Access\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ApprovalStepStatus: string implements HasColor, HasLabel
{
    /** Not reached yet. */
    case Waiting = 'waiting';

    /** Waiting for this approver now. */
    case Pending = 'pending';

    case Approved = 'approved';
    case Rejected = 'rejected';

    /** Not needed (policy, or the request ended earlier). */
    case Skipped = 'skipped';

    public function getLabel(): string
    {
        return match ($this) {
            self::Waiting => 'Not started',
            self::Pending => 'Waiting',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Skipped => 'Skipped',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            default => 'gray',
        };
    }
}
