<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Path on the private "local" disk (branding/…); null = built-in mark.
        $this->migrator->add('general.logo_path', null);
    }

    public function down(): void
    {
        $this->migrator->delete('general.logo_path');
    }
};
