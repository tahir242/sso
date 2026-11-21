<?php

namespace App\Domain\Access\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReviewDecision: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Keep = 'keep';
    case Revoke = 'revoke';

    /** Nobody decided before the review closed. */
    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Not reviewed',
            self::Keep => 'Keep access',
            self::Revoke => 'Removed',
            self::Expired => 'Not reviewed in time',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Keep => 'success',
            self::Revoke => 'danger',
            self::Expired => 'warning',
            self::Pending => 'gray',
        };
    }
}
