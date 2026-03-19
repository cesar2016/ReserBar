<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\MenuCategory;
use Illuminate\Support\Facades\Redis;

class MenuService
{
    private EmbeddingService $embeddingService;

    public function __construct(EmbeddingService $embeddingService)
    {
        $this->embeddingService = $embeddingService;
    }

    public function getFullMenu(): array
    {
        $cacheKey = 'menu:full';
        
        try {
            $cached = Redis::get($cacheKey);
            if ($cached) {
                return json_decode($cached, true);
            }
        } catch (\Exception $e) {
        }
        
        $categories = MenuCategory::active()
            ->ordered()
            ->with(['items' => fn($q) => $q->available()->orderBy('name')])
            ->get();
        
        $menu = $categories->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'icon' => $category->icon,
                'items' => $category->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'description' => $item->description,
                        'price' => $item->price,
                        'formatted_price' => $item->formatted_price,
                        'is_featured' => $item->is_featured,
                        'ingredients' => $item->ingredients ?? [],
                    ];
                })->toArray(),
            ];
        })->toArray();
        
        try {
            Redis::setex($cacheKey, 1800, json_encode($menu));
        } catch (\Exception $e) {
        }
        
        return $menu;
    }

    public function getMenuOfTheDay(): array
    {
        $dayMap = [
            0 => 'domingo',
            1 => 'lunes',
            2 => 'martes',
            3 => 'miercoles',
            4 => 'jueves',
            5 => 'viernes',
            6 => 'sabado',
        ];
        
        $today = $dayMap[now()->dayOfWeek];
        
        return MenuItem::available()
            ->whereNotNull('day_of_week')
            ->where('day_of_week', $today)
            ->with('category')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'price' => $item->price,
                    'formatted_price' => $item->formatted_price,
                    'day_of_week' => ucfirst($item->day_of_week),
                ];
            })
            ->toArray();
    }

    public function searchMenu(string $query, int $limit = 5): array
    {
        return $this->embeddingService->searchSimilar($query, $limit);
    }

    public function getItemById(int $id): ?array
    {
        $item = MenuItem::with('category')->find($id);
        
        if (!$item) {
            return null;
        }
        
        return [
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'price' => $item->price,
            'formatted_price' => $item->formatted_price,
            'category' => $item->category->name ?? 'Sin categoría',
            'category_icon' => $item->category->icon ?? '🍽️',
            'ingredients' => $item->ingredients ?? [],
            'is_available' => $item->is_available,
            'is_featured' => $item->is_featured,
        ];
    }

    public function getItemsByCategory(int $categoryId): array
    {
        return MenuItem::available()
            ->where('category_id', $categoryId)
            ->orderBy('name')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'price' => $item->price,
                    'formatted_price' => $item->formatted_price,
                    'is_featured' => $item->is_featured,
                ];
            })
            ->toArray();
    }

    public function clearCache(): void
    {
        try {
            Redis::del('menu:full');
        } catch (\Exception $e) {
        }
    }
}
