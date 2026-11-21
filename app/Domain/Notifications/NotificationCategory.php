<?php

namespace App\Domain\Notifications;

/**
 * What a notification is about. Users choose channels per category.
 */
enum NotificationCategory: string
{
    /** Access requests, approvals and decisions. */
    case Access = 'access';

    /** Security events on the user's own account. */
    case Security = 'security';

    /** Alerts for the IT security team (lockouts, admin role changes, ...). */
    case SecurityTeam = 'security_team';

    /** Support tickets: replies, status changes, new and overdue tickets. */
    case Helpdesk = 'helpdesk';

    public function label(): string
    {
        return match ($this) {
            self::Access => 'Access requests and approvals',
            self::Security => 'Security alerts about my account',
            self::SecurityTeam => 'Security team alerts',
            self::Helpdesk => 'Support tickets',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Access => 'Approvals waiting for you, and decisions on your requests.',
            self::Security => 'New sign-ins, password and two-step verification changes.',
            self::SecurityTeam => 'Account lockouts, administrator role changes and MFA resets across the organization.',
            self::Helpdesk => 'Replies and updates on your tickets; for IT staff, new and overdue tickets.',
        };
    }

    /**
     * @return array<int, Channel>
     */
    public function defaultChannels(): array
    {
        return match ($this) {
            self::Access => [Channel::Mail, Channel::WebPush],
            self::Security => [Channel::Mail, Channel::WhatsApp, Channel::WebPush],
            self::SecurityTeam => [Channel::Mail, Channel::WhatsApp],
            self::Helpdesk => [Channel::Mail, Channel::WebPush],
        };
    }

    /**
     * Security alerts about your own account can't be switched off entirely:
     * at least one outside channel stays on when available.
     */
    public function isMandatory(): bool
    {
        return $this === self::Security;
    }
}
