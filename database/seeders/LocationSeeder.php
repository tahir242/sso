<?php

namespace Database\Seeders;

use App\Domain\Identity\Enums\LocationType;
use App\Domain\Identity\Models\Location;
use Illuminate\Database\Seeder;

/**
 * Starter location tree (sample structure, edit freely in Identity →
 * Location dictionary). Does nothing if locations already exist.
 *
 * php artisan db:seed --class=LocationSeeder
 */
class LocationSeeder extends Seeder
{
    public function run(): void
    {
        if (Location::query()->exists()) {
            return;
        }

        $tree = [
            'Capital City' => ['code' => 'HQ', 'children' => [
                'Main Building' => ['code' => 'MAIN', 'description' => 'Sample building, rename or delete it', 'children' => [
                    'Ground floor' => ['code' => 'G', 'children' => [
                        'Reception' => ['code' => 'G-01', 'children' => ['WS-G01-1' => [], 'WS-G01-2' => []]],
                        'Records Room' => ['code' => 'G-07', 'children' => ['WS-G07-1' => []]],
                    ]],
                    'First floor' => ['code' => 'F1', 'children' => [
                        'Human Resources' => ['code' => 'F1-12', 'children' => ['WS-F112-1' => [], 'WS-F112-2' => []]],
                        'Finance' => ['code' => 'F1-20', 'children' => ['WS-F120-1' => []]],
                    ]],
                    'Second floor' => ['code' => 'F2', 'children' => [
                        'IT Department' => ['code' => 'F2-04', 'children' => ['WS-F204-1' => [], 'WS-F204-2' => [], 'WS-F204-3' => []]],
                        'Server Room' => ['code' => 'F2-05', 'children' => []],
                    ]],
                ]],
            ]],
            'Second City' => ['code' => 'BR', 'children' => [
                'Branch Building' => ['code' => 'BR-1', 'children' => [
                    'Ground floor' => ['code' => 'BR-G', 'children' => [
                        'Front Desk' => ['code' => 'BR-G-01', 'children' => ['WS-BRG01-1' => []]],
                    ]],
                ]],
            ]],
        ];

        $this->create($tree, null, LocationType::City);
    }

    private function create(array $nodes, ?Location $parent, LocationType $type): void
    {
        $order = 0;
        foreach ($nodes as $name => $node) {
            $location = Location::create([
                'parent_id' => $parent?->id,
                'type' => $type,
                'name' => $name,
                'description' => $node['description'] ?? null,
                'sort_order' => $order++,
            ]);

            if (! empty($node['children']) && $type->child()) {
                $this->create($node['children'], $location, $type->child());
            }
        }
    }
}
