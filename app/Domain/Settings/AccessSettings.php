<?php

namespace App\Domain\Settings;

use Spatie\LaravelSettings\Settings;

class AccessSettings extends Settings
{
    /** Hours an approver has before a step is escalated. */
    public int $approval_sla_hours;

    /** Longest access that can be requested in one go (days); 0 = no limit. */
    public int $max_request_days;

    /** Start an access review of every application this often; 0 = only manually. */
    public int $review_interval_months;

    /** Days reviewers get to complete a scheduled review. */
    public int $review_duration_days;

    /** Remove access that nobody confirmed when a scheduled review closes. */
    public bool $review_auto_revoke;

    public static function group(): string
    {
        return 'access';
    }
}
