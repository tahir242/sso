<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('helpdesk.first_response_hours', 4);
        $this->migrator->add('helpdesk.resolve_hours_urgent', 4);
        $this->migrator->add('helpdesk.resolve_hours_high', 24);
        $this->migrator->add('helpdesk.resolve_hours_normal', 72);
        $this->migrator->add('helpdesk.resolve_hours_low', 168);
        $this->migrator->add('helpdesk.auto_close_days', 5);
    }
};
