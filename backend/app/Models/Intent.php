<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Intent extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'required_entities',
        'response_template',
        'example_phrases',
        'embedding',
        'is_active',
    ];

    protected $casts = [
        'required_entities' => 'array',
        'example_phrases' => 'array',
        'embedding' => 'array',
        'is_active' => 'boolean',
    ];

    public function conversations(): HasMany
    {
        return $this->hasMany(ConversationEmbedding::class, 'intent_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getEmbeddingTextAttribute(): string
    {
        $phrases = implode(' ', $this->example_phrases ?? []);
        return "{$this->name} {$this->description} {$phrases}";
    }
}
