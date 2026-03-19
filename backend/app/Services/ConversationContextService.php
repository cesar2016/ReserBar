<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class ConversationContextService
{
    private const CONTEXT_PREFIX = 'chat:context:';
    private const CONTEXT_TTL = 1800;
    
    private IntentEmbeddingService $intentService;

    public function __construct(IntentEmbeddingService $intentService)
    {
        $this->intentService = $intentService;
    }

    public function getOrCreateSession(?int $userId): string
    {
        if ($userId) {
            try {
                $existing = Redis::get(self::CONTEXT_PREFIX . "user:{$userId}");
                if ($existing) {
                    return $existing;
                }
            } catch (\Exception $e) {}
        }
        
        return Str::uuid()->toString();
    }

    public function getContext(?int $userId, string $sessionId): array
    {
        $key = $userId ? "user:{$userId}" : "session:{$sessionId}";
        
        try {
            $cached = Redis::get(self::CONTEXT_PREFIX . $key);
            if ($cached) {
                return json_decode($cached, true);
            }
        } catch (\Exception $e) {}
        
        return $this->createEmptyContext($userId, $sessionId);
    }

    public function createEmptyContext(?int $userId, string $sessionId): array
    {
        return [
            'user_id' => $userId,
            'session_id' => $sessionId,
            'step' => 'initial',
            'intent' => null,
            'entities' => [
                'date' => null,
                'time' => null,
                'guest_count' => null,
            ],
            'intent_confidence' => 0,
            'conversation_history' => [],
            'started_at' => now()->toIso8601String(),
        ];
    }

    public function updateContext(?int $userId, string $sessionId, array $updates): array
    {
        $key = $userId ? "user:{$userId}" : "session:{$sessionId}";
        $context = $this->getContext($userId, $sessionId);
        
        foreach ($updates as $k => $v) {
            if ($k === 'entities') {
                $context['entities'] = array_merge($context['entities'] ?? [], $v);
            } else {
                $context[$k] = $v;
            }
        }
        
        $context['updated_at'] = now()->toIso8601String();
        
        try {
            Redis::setex(self::CONTEXT_PREFIX . $key, self::CONTEXT_TTL, json_encode($context));
        } catch (\Exception $e) {}
        
        return $context;
    }

    public function addToHistory(?int $userId, string $sessionId, string $role, string $message): array
    {
        $context = $this->getContext($userId, $sessionId);
        
        $context['conversation_history'][] = [
            'role' => $role,
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
        ];
        
        if (count($context['conversation_history']) > 20) {
            $context['conversation_history'] = array_slice($context['conversation_history'], -20);
        }
        
        $key = $userId ? "user:{$userId}" : "session:{$sessionId}";
        
        try {
            Redis::setex(self::CONTEXT_PREFIX . $key, self::CONTEXT_TTL, json_encode($context));
        } catch (\Exception $e) {}
        
        return $context;
    }

    public function clearContext(?int $userId, string $sessionId): void
    {
        $key = $userId ? "user:{$userId}" : "session:{$sessionId}";
        
        try {
            Redis::del(self::CONTEXT_PREFIX . $key);
        } catch (\Exception $e) {}
    }

    public function processMessage(string $message, ?int $userId, ?string $sessionId): array
    {
        $intent = $this->intentService->detectIntent($message);
        $entities = $this->intentService->extractEntities($message);
        
        $context = $this->getContext($userId, $sessionId);
        
        if ($context['intent'] && $context['intent'] !== $intent?->name) {
            $context = $this->createEmptyContext($userId, $sessionId);
        }
        
        if ($intent) {
            $context['intent'] = $intent->name;
            $context['intent_id'] = $intent->id;
        }
        
        $context['entities'] = array_merge($context['entities'] ?? [], $entities);
        $context['step'] = $this->determineStep($context);
        
        $key = $userId ? "user:{$userId}" : "session:{$sessionId}";
        
        try {
            Redis::setex(self::CONTEXT_PREFIX . $key, self::CONTEXT_TTL, json_encode($context));
        } catch (\Exception $e) {}
        
        return [
            'context' => $context,
            'intent' => $intent,
            'entities' => $entities,
        ];
    }

    private function determineStep(array $context): string
    {
        $entities = $context['entities'] ?? [];
        $hasDate = !empty($entities['date']);
        $hasTime = !empty($entities['time']);
        $hasGuests = !empty($entities['guest_count']);
        
        if (!$context['intent']) {
            return 'initial';
        }
        
        if ($context['intent'] === 'greeting') {
            return 'greeting';
        }
        
        if ($context['intent'] === 'menu_query') {
            return 'menu';
        }
        
        if ($context['intent'] === 'make_reservation') {
            if (!$hasDate) return 'collecting_date';
            if (!$hasTime) return 'collecting_time';
            if (!$hasGuests) return 'collecting_guests';
            return 'confirming';
        }
        
        if ($context['intent'] === 'cancel_reservation') {
            return 'cancel';
        }
        
        return 'initial';
    }

    public function getMissingEntities(array $context): array
    {
        $missing = [];
        $entities = $context['entities'] ?? [];
        
        if (empty($entities['date'])) {
            $missing[] = 'date';
        }
        if (empty($entities['time'])) {
            $missing[] = 'time';
        }
        if (empty($entities['guest_count'])) {
            $missing[] = 'guest_count';
        }
        
        return $missing;
    }

    public function isReadyForConfirmation(array $context): bool
    {
        $entities = $context['entities'] ?? [];
        return !empty($entities['date']) && 
               !empty($entities['time']) && 
               !empty($entities['guest_count']);
    }
}
