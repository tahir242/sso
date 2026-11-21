<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Reference data only. The first administrator is created interactively
     * with `php artisan sso:create-admin` so no default password ever exists.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            OccasionSeeder::class,
            QuoteSeeder::class,
            TicketCategorySeeder::class,
        ]);
    }
}
