<?php

namespace App\Domain\Audit\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AuthEventType: string implements HasColor, HasLabel
{
    case LoginSucceeded = 'login.succeeded';
    case LoginFailed = 'login.failed';
    case LoginLocked = 'login.locked';
    case Logout = 'logout';
    case SessionExpired = 'session.expired';
    case MfaChallenged = 'mfa.challenged';
    case MfaSucceeded = 'mfa.succeeded';
    case MfaFailed = 'mfa.failed';
    case MfaRecoveryCodeUsed = 'mfa.recovery_code_used';
    case MfaEnabled = 'mfa.enabled';
    case MfaDisabled = 'mfa.disabled';
    case MfaReset = 'mfa.reset_by_admin';
    case TrustedDeviceAdded = 'device.trusted';
    case TrustedDeviceRevoked = 'device.revoked';
    case PasswordChanged = 'password.changed';
    case UserProvisioned = 'user.provisioned';

    public function getLabel(): string
    {
        return match ($this) {
            self::LoginSucceeded => 'Signed in',
            self::LoginFailed => 'Sign-in failed',
            self::LoginLocked => 'Sign-in blocked (too many attempts)',
            self::Logout => 'Signed out',
            self::SessionExpired => 'Session expired',
            self::MfaChallenged => 'Asked for a code',
            self::MfaSucceeded => 'Code accepted',
            self::MfaFailed => 'Wrong code entered',
            self::MfaRecoveryCodeUsed => 'Recovery code used',
            self::MfaEnabled => 'Two-step verification turned on',
            self::MfaDisabled => 'Two-step verification turned off',
            self::MfaReset => 'Two-step verification reset by admin',
            self::TrustedDeviceAdded => 'Computer remembered',
            self::TrustedDeviceRevoked => 'Remembered computer removed',
            self::PasswordChanged => 'Password changed',
            self::UserProvisioned => 'Account created from Active Directory',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::LoginFailed, self::LoginLocked, self::MfaFailed => 'danger',
            self::MfaReset, self::MfaDisabled, self::MfaRecoveryCodeUsed, self::SessionExpired => 'warning',
            self::LoginSucceeded, self::MfaSucceeded, self::MfaEnabled => 'success',
            default => 'gray',
        };
    }
}
