<?php

namespace App\Domain\Requests\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ChangeRequestStatus: string implements HasColor, HasLabel
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Declined = 'declined';

    public function getLabel(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under review',
            self::Planned => 'Planned',
            self::InProgress => 'In progress',
            self::Done => 'Done',
            self::Declined => 'Declined',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Submitted, self::UnderReview => 'warning',
            self::Planned, self::InProgress => 'info',
            self::Done => 'success',
            self::Declined => 'danger',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Done, self::Declined], true);
    }
}
