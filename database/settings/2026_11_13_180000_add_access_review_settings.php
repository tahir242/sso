<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('access.review_interval_months', 3);
        $this->migrator->add('access.review_duration_days', 14);
        $this->migrator->add('access.review_auto_revoke', false);
    }
};
