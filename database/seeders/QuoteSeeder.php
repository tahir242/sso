<?php

namespace Database\Seeders;

use App\Domain\Engagement\Models\Quote;
use Illuminate\Database\Seeder;

/**
 * Starter quotes for the login screen; more are managed in the admin console.
 */
class QuoteSeeder extends Seeder
{
    public function run(): void
    {
        $quotes = [
            ['Unity, Faith, Discipline.', 'Quaid-e-Azam Muhammad Ali Jinnah'],
            ['The good physician treats the disease; the great physician treats the patient who has the disease.', 'William Osler'],
            ['Wherever the art of medicine is loved, there is also a love of humanity.', 'Hippocrates'],
            ['Alone we can do so little; together we can do so much.', 'Helen Keller'],
            ['Quality is never an accident; it is always the result of intelligent effort.', 'John Ruskin'],
        ];

        foreach ($quotes as [$text, $author]) {
            Quote::firstOrCreate(['text' => $text], ['author' => $author, 'active' => true]);
        }
    }
}
