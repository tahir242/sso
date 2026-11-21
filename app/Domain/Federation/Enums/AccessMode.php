<?php

namespace App\Domain\Federation\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Who may sign in to an application.
 */
enum AccessMode: string implements HasDescription, HasLabel
{
    /** Any active account, no access request needed (e.g. intranet tools). */
    case Everyone = 'everyone';

    /** Only users given access (by an administrator or an approved request). */
    case Assigned = 'assigned';

    public function getLabel(): string
    {
        return match ($this) {
            self::Everyone => 'Everyone',
            self::Assigned => 'Assigned users only',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Everyone => 'Every active account can open it. No access request needed; users get the default roles.',
            self::Assigned => 'Only people given access by an administrator or through an approved access request.',
        };
    }
}
