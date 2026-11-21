<?php

namespace App\Domain\Requests\Enums;

use Filament\Support\Contracts\HasLabel;

enum ChangeRequestType: string implements HasLabel
{
    case Feature = 'feature';
    case Change = 'change';
    /**
     * No longer offered: problems go to the IT helpdesk as a ticket, so they
     * are handled in one place. Kept for suggestions sent before that.
     */
    case Bug = 'bug';

    public function getLabel(): string
    {
        return match ($this) {
            self::Feature => 'Something new',
            self::Change => 'Change how something works',
            self::Bug => 'Problem report',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Feature => 'A new report, field, screen or option.',
            self::Change => 'Something that exists but would work better another way.',
            self::Bug => 'Something was not working.',
        };
    }

    /**
     * What staff can choose when they suggest an improvement.
     *
     * @return array<int, self>
     */
    public static function offered(): array
    {
        return [self::Feature, self::Change];
    }
}
