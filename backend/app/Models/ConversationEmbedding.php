<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationEmbedding extends Model
{
    use HasFactory;

    protected $table = 'conversation_embeddings';

    protected $fillable = [
        'user_id',
        'intent_id',
        'user_message',
        'assistant_message',
        'extracted_entities',
        'embedding',
        'confidence',
        'session_id',
        'was_successful',
    ];

    protected $casts = [
        'extracted_entities' => 'array',
        'embedding' => 'array',
        'confidence' => 'float',
        'was_successful' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function intent(): BelongsTo
    {
        return $this->belongsTo(Intent::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForSession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }
}
