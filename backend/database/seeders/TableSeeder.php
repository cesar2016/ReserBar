<?php

namespace Database\Seeders;

use App\Models\Table;
use Illuminate\Database\Seeder;

class TableSeeder extends Seeder
{
    public function run(): void
    {
        Table::truncate();
        $tables = [
            ['location' => 'A', 'number' => 1, 'capacity' => 2],
            ['location' => 'A', 'number' => 2, 'capacity' => 2],
            ['location' => 'B', 'number' => 1, 'capacity' => 2],
            ['location' => 'B', 'number' => 2, 'capacity' => 2],
            ['location' => 'C', 'number' => 1, 'capacity' => 4],
            ['location' => 'C', 'number' => 2, 'capacity' => 4],
            ['location' => 'D', 'number' => 1, 'capacity' => 4],
            ['location' => 'D', 'number' => 2, 'capacity' => 4],
        ];

        foreach ($tables as $table) {
            \App\Models\Table::create($table);
        }
    }
}
