<?php

use NotificationChannels\WebPush\PushSubscription;

/*
| Browser push notifications. The VAPID keys are NOT read from .env: they are
| generated and stored (encrypted) in the database settings and applied at
| boot by SettingsServiceProvider (Admin -> Settings -> Notifications).
*/
return [

    'vapid' => [
        'subject' => null,
        'public_key' => null,
        'private_key' => null,
        'pem_file' => null,
    ],

    'model' => PushSubscription::class,

    'table_name' => 'push_subscriptions',

    // null = the application's default connection
    'database_connection' => null,

    'client_options' => [],

    'automatic_padding' => true,

];
