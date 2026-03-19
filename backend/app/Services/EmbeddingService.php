<?php

namespace App\Services;

use App\Models\MenuItem;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

class EmbeddingService
{
    private const CACHE_PREFIX = 'menu:embeddings:';
    private const CACHE_TTL = 3600;
    private const EMBEDDING_DIM = 384;

    private array $stopWords = [
        'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas',
        'de', 'del', 'al', 'con', 'por', 'para', 'sin', 'sobre',
        'y', 'e', 'o', 'u', 'pero', 'mas', 'que', 'a', 'en',
        'es', 'son', 'fue', 'con', 'su', 'se', 'le', 'lo'
    ];

    public function generateTextEmbedding(string $text): array
    {
        $text = strtolower($text);
        $words = preg_split('/\s+/', $text);
        $words = array_filter($words, fn($w) => strlen($w) > 2);
        $words = array_diff($words, $this->stopWords);
        
        $embedding = array_fill(0, self::EMBEDDING_DIM, 0.0);
        
        foreach ($words as $word) {
            $wordVector = $this->wordToVector($word);
            for ($i = 0; $i < self::EMBEDDING_DIM; $i++) {
                $embedding[$i] += $wordVector[$i];
            }
        }
        
        $magnitude = sqrt(array_sum(array_map(fn($x) => $x * $x, $embedding)));
        if ($magnitude > 0) {
            $embedding = array_map(fn($x) => $x / $magnitude, $embedding);
        }
        
        return $embedding;
    }

    private function wordToVector(string $word): array
    {
        $vector = array_fill(0, self::EMBEDDING_DIM, 0.0);
        $len = strlen($word);
        
        for ($i = 0; $i < $len && $i < self::EMBEDDING_DIM; $i++) {
            $vector[$i] = (ord($word[$i]) / 255.0) * 0.5;
        }
        
        $hash = crc32($word);
        for ($i = 0; $i < self::EMBEDDING_DIM; $i++) {
            $pos = ($hash + $i * 17) % self::EMBEDDING_DIM;
            $vector[$pos] += sin($i + $hash) * 0.3;
        }
        
        return $vector;
    }

    public function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return 0.0;
        }
        
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        
        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }
        
        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }
        
        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    public function searchSimilar(string $query, int $limit = 5, ?int $categoryId = null): array
    {
        $cacheKey = self::CACHE_PREFIX . 'search:' . md5($query . ':' . $limit . ':' . $categoryId);
        
        try {
            $cached = Redis::get($cacheKey);
            if ($cached) {
                return json_decode($cached, true);
            }
        } catch (\Exception $e) {
        }
        
        $queryEmbedding = $this->generateTextEmbedding($query);
        
        $menuItems = MenuItem::available()
            ->with('category')
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
            ->get();
        
        $results = [];
        
        foreach ($menuItems as $item) {
            $searchText = $item->name . ' ' . $item->description . ' ' . 
                         implode(' ', $item->ingredients ?? []);
            
            $itemEmbedding = $this->generateTextEmbedding($searchText);
            $similarity = $this->cosineSimilarity($queryEmbedding, $itemEmbedding);
            
            if ($similarity > 0.1) {
                $results[] = [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'price' => $item->price,
                    'formatted_price' => $item->formatted_price,
                    'category' => $item->category->name ?? 'Sin categoría',
                    'category_icon' => $item->category->icon ?? '🍽️',
                    'similarity' => round($similarity, 3),
                ];
            }
        }
        
        usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
        $results = array_slice($results, 0, $limit);
        
        try {
            Redis::setex($cacheKey, self::CACHE_TTL, json_encode($results));
        } catch (\Exception $e) {
        }
        
        return $results;
    }

    public function rebuildEmbeddings(): int
    {
        $items = MenuItem::all();
        $count = 0;
        
        foreach ($items as $item) {
            $text = $item->name . ' ' . $item->description . ' ' . 
                   implode(' ', $item->ingredients ?? []);
            
            $embedding = $this->generateTextEmbedding($text);
            
            $item->update(['embedding' => json_encode($embedding)]);
            $count++;
        }
        
        try {
            Redis::flushdb();
        } catch (\Exception $e) {
        }
        
        return $count;
    }

    public function getKeywords(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $words = preg_split('/\s+/', $text);
        $words = array_filter($words, fn($w) => strlen($w) > 2);
        $words = array_diff($words, $this->stopWords);
        
        return array_values($words);
    }
}
