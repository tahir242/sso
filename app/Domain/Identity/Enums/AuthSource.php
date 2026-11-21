<?php

namespace App\Domain\Identity\Enums;

use Filament\Support\Contracts\HasLabel;

enum AuthSource: string implements HasLabel
{
    /** Password verified by an LDAP bind against Active Directory. */
    case ActiveDirectory = 'ad';

    /** Password stored (Argon2id) in the SSO database. */
    case Local = 'local';

    /**
     * Accepts an enum instance or its raw value (as form state may be either).
     */
    public static function of(mixed $value): ?self
    {
        return $value instanceof self ? $value : self::tryFrom((string) $value);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::ActiveDirectory => 'Active Directory',
            self::Local => 'Local account',
        };
    }
}
