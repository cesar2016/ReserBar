<?php

namespace App\Services;

use App\Models\Intent;
use App\Models\ConversationEmbedding;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class IntentEmbeddingService
{
    private const EMBEDDING_DIM = 384;
    private const SIMILARITY_THRESHOLD = 0.3;
    private const CACHE_PREFIX = 'intent:embeddings:';
    private const CACHE_TTL = 3600;

    private array $stopWords = [
        'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas',
        'de', 'del', 'al', 'con', 'por', 'para', 'sin', 'sobre',
        'y', 'e', 'o', 'u', 'pero', 'mas', 'que', 'a', 'en',
        'es', 'son', 'fue', 'su', 'se', 'le', 'lo', 'me', 'te',
        'nos', 'os', 'mi', 'tu', 'si', 'no', 'hoy', 'mañana', 'ayer'
    ];

    private array $entityPatterns = [
        'date' => [
            '/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/',  // 26/03/2026
            '/(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/',  // 2026-03-26
            '/(\d{1,2})\s+de\s+(\w+)/i',  // 26 de marzo
            '/(\w+)\s+(\d{1,2})/i',  // lunes 26
            '/hoy/i', '/mañana/i', '/pasado\s+mañana/i',
        ],
        'time' => [
            '/(\d{1,2}):(\d{2})/',  // 21:00
            '/(\d{1,2})\s*(am|pm|hs|horas?)/i',  // 9pm, 21hs
            '/mediod[ií]a/i', '/medianoche/i', '/al\s+mediod[ií]a/i',
            '/a\s+las\s+(\d{1,2})/i',  // a las 9
        ],
        'guests' => [
            '/(\d+)\s*(personas?|comensales?|gente|somos|van|iran?|vamos)/i',
            '/(para\s+)?dos?\b/i' => 2,
            '/(para\s+)?tres?\b/i' => 3,
            '/(para\s+)?cuatro?\b/i' => 4,
            '/(para\s+)?cinco?\b/i' => 5,
            '/(para\s+)?seis?\b/i' => 6,
            '/(para\s+)?siete?\b/i' => 7,
            '/(para\s+)?ocho?\b/i' => 8,
        ],
    ];

    private array $dayMapping = [
        'domingo' => 0, 'lunes' => 1, 'martes' => 2, 'miercoles' => 3,
        'jueves' => 4, 'viernes' => 5, 'sabado' => 6, 'sábado' => 6,
        'ene' => 1, 'feb' => 2, 'mar' => 3, 'abr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'ago' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dic' => 12,
    ];

    public function generateEmbedding(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $words = preg_split('/\s+/', $text);
        $words = array_filter($words, fn($w) => strlen($w) > 2);
        $words = array_diff($words, $this->stopWords);
        
        $embedding = array_fill(0, self::EMBEDDING_DIM, 0.0);
        
        foreach ($words as $i => $word) {
            $wordVector = $this->wordToVector($word, $i);
            for ($j = 0; $j < self::EMBEDDING_DIM; $j++) {
                $embedding[$j] += $wordVector[$j];
            }
        }
        
        $magnitude = sqrt(array_sum(array_map(fn($x) => $x * $x, $embedding)));
        if ($magnitude > 0) {
            $embedding = array_map(fn($x) => $x / $magnitude, $embedding);
        }
        
        return $embedding;
    }

    private function wordToVector(string $word, int $position): array
    {
        $vector = array_fill(0, self::EMBEDDING_DIM, 0.0);
        $len = strlen($word);
        
        for ($i = 0; $i < $len && $i < 50; $i++) {
            $pos = ($i * 17) % self::EMBEDDING_DIM;
            $vector[$pos] += (ord($word[$i]) / 255.0) * 0.6;
        }
        
        $hash = crc32($word);
        for ($i = 0; $i < 30; $i++) {
            $pos = ($hash + $i * 31 + $position * 7) % self::EMBEDDING_DIM;
            $vector[$pos] += sin($i + $hash) * 0.4;
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

    public function detectIntent(string $message): ?Intent
    {
        $messageLower = strtolower($message);
        
        $greetingPatterns = ['hola', 'buenos días', 'buenas tardes', 'buenas noches', 'que tal', 'buen día'];
        foreach ($greetingPatterns as $pattern) {
            if (strpos($messageLower, $pattern) !== false && strlen($message) < 30) {
                return Intent::where('name', 'greeting')->first();
            }
        }
        
        $reservationPatterns = [
            'reservar', 'reserva', 'reservación', 'mesa', 'sentar', 'sitio',
            'quiero ir', 'vamos a comer', 'cenar', 'almorzar', 'jantar',
            'hacer una', 'pedir una'
        ];
        $hasReservationIntent = false;
        foreach ($reservationPatterns as $pattern) {
            if (strpos($messageLower, $pattern) !== false) {
                $hasReservationIntent = true;
                break;
            }
        }
        
        if ($hasReservationIntent) {
            return Intent::where('name', 'make_reservation')->first();
        }
        
        $cancelPatterns = ['cancelar', 'anular', 'eliminar reserva', 'ya no puedo', 'cambiar fecha'];
        foreach ($cancelPatterns as $pattern) {
            if (strpos($messageLower, $pattern) !== false) {
                return Intent::where('name', 'cancel_reservation')->first();
            }
        }
        
        $menuPatterns = ['menú', 'menu', 'carta', 'comida', 'platos', 'comer', 'pedir'];
        foreach ($menuPatterns as $pattern) {
            if (strpos($messageLower, $pattern) !== false) {
                return Intent::where('name', 'menu_query')->first();
            }
        }
        
        $queryPatterns = ['mis reservas', 'ver reservas', 'cuando tengo', 'mi reserva'];
        foreach ($queryPatterns as $pattern) {
            if (strpos($messageLower, $pattern) !== false) {
                return Intent::where('name', 'query_reservation')->first();
            }
        }
        
        $intents = Intent::active()->get();
        $queryEmbedding = $this->generateEmbedding($message);
        
        $bestMatch = null;
        $bestScore = 0;
        
        foreach ($intents as $intent) {
            $intentText = $intent->getEmbeddingTextAttribute();
            $intentEmbedding = $this->generateEmbedding($intentText);
            $similarity = $this->cosineSimilarity($queryEmbedding, $intentEmbedding);
            
            if ($similarity > $bestScore) {
                $bestScore = $similarity;
                $bestMatch = $intent;
            }
        }
        
        if ($bestScore >= self::SIMILARITY_THRESHOLD) {
            return $bestMatch;
        }
        
        return Intent::where('name', 'unknown')->first();
    }

    public function extractEntities(string $message): array
    {
        $entities = [];
        $messageLower = strtolower($message);
        
        $date = $this->extractDate($message, $messageLower);
        if ($date) {
            $entities['date'] = $date;
        }
        
        $time = $this->extractTime($message, $messageLower);
        if ($time) {
            $entities['time'] = $time;
        }
        
        $guests = $this->extractGuests($message, $messageLower);
        if ($guests) {
            $entities['guest_count'] = $guests;
        }
        
        return $entities;
    }

    private function extractDate(string $message, string $messageLower): ?string
    {
        if (preg_match('/(\d{4})-(\d{1,2})-(\d{1,2})/', $message, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        
        if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/', $message, $m)) {
            $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($m[2], 2, '0', STR_PAD_LEFT);
            $year = strlen($m[3]) == 2 ? '20' . $m[3] : $m[3];
            return "{$year}-{$month}-{$day}";
        }
        
        if (preg_match('/(\d{1,2})\s+de\s+(\w+)/i', $message, $m)) {
            $day = $m[1];
            $monthName = strtolower(substr($m[2], 0, 3));
            if (isset($this->dayMapping[$monthName])) {
                $month = $this->dayMapping[$monthName];
                $year = date('Y');
                if ($month < date('n') || ($month == date('n') && $day < date('j'))) {
                    $year++;
                }
                return sprintf("%04d-%02d-%02d", $year, $month, $day);
            }
        }
        
        $dayOfWeekPattern = '/(lunes|martes|miercoles|jueves|viernes|sabado|sábado|domingo)/i';
        if (preg_match($dayOfWeekPattern, $messageLower, $m)) {
            $dayName = strtolower($m[1]);
            if ($dayName === 'sábado') $dayName = 'sabado';
            $targetDay = $this->dayMapping[$dayName];
            $currentDay = date('w');
            $daysUntil = ($targetDay - $currentDay + 7) % 7;
            if ($daysUntil == 0) $daysUntil = 7;
            return date('Y-m-d', strtotime("+{$daysUntil} days"));
        }
        
        if (strpos($messageLower, 'hoy') !== false) {
            return date('Y-m-d');
        }
        
        if (strpos($messageLower, 'mañana') !== false || strpos($messageLower, 'manana') !== false) {
            return date('Y-m-d', strtotime('+1 day'));
        }
        
        if (preg_match('/este mes/i', $messageLower) && preg_match('/(\d{1,2})/', $message, $m)) {
            return date('Y-m-') . str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }
        
        if (preg_match('/para el\s+(\d{1,2})\s+a\s+las/i', $messageLower, $m)) {
            $day = (int)$m[1];
            $month = (int)date('m');
            $year = (int)date('Y');
            if ($day < (int)date('j')) {
                $month++;
                if ($month > 12) {
                    $month = 1;
                    $year++;
                }
            }
            return sprintf("%04d-%02d-%02d", $year, $month, $day);
        }
        
        if (preg_match('/(\d{1,2})\s+(?:de\s+)?(?:este\s+mes|mes\s+que\s+viene)/i', $messageLower, $m)) {
            $day = (int)$m[1];
            $month = (int)date('m');
            $year = (int)date('Y');
            if ($day < (int)date('j')) {
                $month++;
                if ($month > 12) {
                    $month = 1;
                    $year++;
                }
            }
            return sprintf("%04d-%02d-%02d", $year, $month, $day);
        }
        
        return null;
    }

    private function extractTime(string $message, string $messageLower): ?string
    {
        if (preg_match('/(\d{1,2}):(\d{2})/', $message, $m)) {
            return sprintf("%02d:%02d", (int)$m[1], (int)$m[2]);
        }
        
        if (preg_match('/(\d{1,2})\s*(am|pm)/i', $message, $m)) {
            $hour = (int)$m[1];
            if (strtolower($m[2]) === 'pm' && $hour < 12) {
                $hour += 12;
            } elseif (strtolower($m[2]) === 'am' && $hour === 12) {
                $hour = 0;
            }
            return sprintf("%02d:00", $hour);
        }
        
        if (preg_match('/(\d{1,2})\s*(hs|horas?)/i', $message, $m)) {
            return sprintf("%02d:00", (int)$m[1]);
        }
        
        if (strpos($messageLower, 'mediodia') !== false || strpos($messageLower, 'mediodía') !== false) {
            return '13:00';
        }
        
        if (strpos($messageLower, 'medianoche') !== false) {
            return '00:00';
        }
        
        if (preg_match('/a\s+las\s+(\d{1,2})/i', $message, $m)) {
            return sprintf("%02d:00", (int)$m[1]);
        }
        
        return null;
    }

    private function extractGuests(string $message, string $messageLower): ?int
    {
        if (preg_match('/(\d+)\s*(?:personas?|comensales?)/i', $message, $m)) {
            $count = (int)$m[1];
            if ($count >= 1 && $count <= 20) {
                return $count;
            }
        }
        
        if (preg_match('/(somos|van|iremos|vamos)\s+(\d+)/i', $message, $m)) {
            $count = (int)$m[2];
            if ($count >= 1 && $count <= 20) {
                return $count;
            }
        }
        
        if (preg_match('/(\d+)\s*(?:personas?|comensales?|gente)\b/i', $message, $m)) {
            $count = (int)$m[1];
            if ($count >= 1 && $count <= 20) {
                return $count;
            }
        }
        
        $wordPatterns = [
            'dos personas' => 2, 'tres personas' => 3, 'cuatro personas' => 4,
            'cinco personas' => 5, 'seis personas' => 6, 'siete personas' => 7,
            'ocho personas' => 8, 'para dos' => 2, 'para tres' => 3, 'para cuatro' => 4,
            'para cinco' => 5, 'para seis' => 6, 'para siete' => 7, 'para ocho' => 8,
        ];
        
        foreach ($wordPatterns as $phrase => $count) {
            if (stripos($message, $phrase) !== false) {
                return $count;
            }
        }
        
        return null;
    }

    public function saveConversation(
        ?int $userId,
        string $message,
        ?string $response,
        ?Intent $intent,
        array $entities,
        float $confidence,
        ?string $sessionId = null
    ): ConversationEmbedding {
        $embedding = $this->generateEmbedding($message);
        
        return ConversationEmbedding::create([
            'user_id' => $userId,
            'intent_id' => $intent?->id,
            'user_message' => $message,
            'assistant_message' => $response,
            'extracted_entities' => $entities,
            'embedding' => json_encode($embedding),
            'confidence' => $confidence,
            'session_id' => $sessionId,
            'was_successful' => true,
        ]);
    }

    public function rebuildIntentEmbeddings(): int
    {
        $intents = Intent::all();
        $count = 0;
        
        foreach ($intents as $intent) {
            $text = $intent->getEmbeddingTextAttribute();
            $embedding = $this->generateEmbedding($text);
            $intent->update(['embedding' => json_encode($embedding)]);
            $count++;
        }
        
        try {
            Redis::flushdb();
        } catch (\Exception $e) {}
        
        return $count;
    }
}
