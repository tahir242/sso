<?php

namespace Database\Seeders;

use App\Domain\Helpdesk\Models\TicketCategory;
use Illuminate\Database\Seeder;

/**
 * General IT helpdesk categories, offered for every application.
 */
class TicketCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['Password or sign-in', 'Locked out, forgotten password, two-step verification'],
            ['Error or not working', 'An application shows an error or does the wrong thing'],
            ['Printing', 'Printer not printing, blank or wrong output'],
            ['Computer or hardware', 'PC, monitor, keyboard, scanner'],
            ['Network or internet', 'No connection, slow, Wi-Fi'],
            ['Question or how-to', 'How do I…?'],
        ];

        foreach ($categories as $i => [$name, $description]) {
            TicketCategory::firstOrCreate(
                ['name' => $name, 'application_id' => null],
                ['description' => $description, 'sort_order' => $i, 'active' => true],
            );
        }
    }
}
