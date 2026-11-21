<?php

namespace App\Domain\Identity\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserStatus: string implements HasLabel
{
    case Active = 'active';
    case Disabled = 'disabled';
    case Locked = 'locked';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function canSignIn(): bool
    {
        return $this === self::Active;
    }
}
