<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'price',
        'day_of_week',
        'ingredients',
        'embedding',
        'is_available',
        'is_featured',
        'image_url',
        'preparation_time',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'ingredients' => 'array',
        'embedding' => 'array',
        'is_available' => 'boolean',
        'is_featured' => 'boolean',
        'preparation_time' => 'integer',
    ];

    protected $hidden = [
        'embedding',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id');
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeForDay($query, string $day)
    {
        return $query->where(function($q) use ($day) {
            $q->whereNull('day_of_week')
              ->orWhere('day_of_week', $day);
        });
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('name', 'LIKE', "%{$search}%")
              ->orWhere('description', 'LIKE', "%{$search}%");
        });
    }

    public function getFormattedPriceAttribute()
    {
        return '$' . number_format($this->price, 0, ',', '.');
    }
}
