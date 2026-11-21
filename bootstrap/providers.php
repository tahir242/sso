<?php

use App\Providers\AppServiceProvider;
use App\Providers\FederationServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\SettingsServiceProvider;

return [
    AppServiceProvider::class,
    SettingsServiceProvider::class,
    FederationServiceProvider::class,
    AdminPanelProvider::class,
];
