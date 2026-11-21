<?php

namespace App\Domain\Access\Enums;

use Filament\Support\Contracts\HasLabel;

enum ApprovalStepType: string implements HasLabel
{
    case HeadOfDepartment = 'hod';
    case ApplicationOwner = 'app_owner';
    case ItSecurity = 'it_security';

    public function getLabel(): string
    {
        return match ($this) {
            self::HeadOfDepartment => 'Head of department',
            self::ApplicationOwner => 'Application owner',
            self::ItSecurity => 'IT security',
        };
    }

    /**
     * Who may decide when no named approver is set (or as a fallback).
     *
     * @return array<int, string>
     */
    public function fallbackRoles(): array
    {
        return match ($this) {
            self::ItSecurity => ['it-security', 'super-admin'],
            default => ['super-admin'],
        };
    }
}
