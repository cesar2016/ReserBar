<?php

namespace App\Http\Controllers;

use App\Services\MenuService;
use App\Services\EmbeddingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MenuController extends Controller
{
    private MenuService $menuService;
    private EmbeddingService $embeddingService;

    public function __construct(MenuService $menuService, EmbeddingService $embeddingService)
    {
        $this->menuService = $menuService;
        $this->embeddingService = $embeddingService;
    }

    public function index(): JsonResponse
    {
        $menu = $this->menuService->getFullMenu();
        
        return response()->json([
            'success' => true,
            'data' => $menu,
        ]);
    }

    public function ofTheDay(): JsonResponse
    {
        $menu = $this->menuService->getMenuOfTheDay();
        
        return response()->json([
            'success' => true,
            'data' => $menu,
            'day' => now()->dayName,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $item = $this->menuService->getItemById($id);
        
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item no encontrado',
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $item,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        $limit = $request->input('limit', 5);
        
        if (strlen($query) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'La búsqueda debe tener al menos 2 caracteres',
            ], 400);
        }
        
        $results = $this->menuService->searchMenu($query, $limit);
        
        return response()->json([
            'success' => true,
            'query' => $query,
            'data' => $results,
        ]);
    }

    public function byCategory(int $categoryId): JsonResponse
    {
        $items = $this->menuService->getItemsByCategory($categoryId);
        
        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function rebuildEmbeddings(): JsonResponse
    {
        $count = $this->embeddingService->rebuildEmbeddings();
        
        return response()->json([
            'success' => true,
            'message' => "Embeddings regenerados para {$count} items",
            'count' => $count,
        ]);
    }
}
