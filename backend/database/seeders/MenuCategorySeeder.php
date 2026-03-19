<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use Illuminate\Database\Seeder;

class MenuCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Menú del Día',
                'slug' => 'menu-dia',
                'icon' => '🍽️',
                'sort_order' => 1,
            ],
            [
                'name' => 'Platos Principales',
                'slug' => 'principales',
                'icon' => '🍖',
                'sort_order' => 2,
            ],
            [
                'name' => 'Minutas',
                'slug' => 'minutas',
                'icon' => '🍟',
                'sort_order' => 3,
            ],
            [
                'name' => 'Bebidas con Alcohol',
                'slug' => 'bebidas-alcohol',
                'icon' => '🍷',
                'sort_order' => 4,
            ],
            [
                'name' => 'Bebidas sin Alcohol',
                'slug' => 'bebidas-sin-alcohol',
                'icon' => '🥤',
                'sort_order' => 5,
            ],
            [
                'name' => 'Postres',
                'slug' => 'postres',
                'icon' => '🍮',
                'sort_order' => 6,
            ],
        ];

        foreach ($categories as $category) {
            MenuCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}
