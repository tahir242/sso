<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('access.approval_sla_hours', 48);
        $this->migrator->add('access.max_request_days', 0);
    }
};
