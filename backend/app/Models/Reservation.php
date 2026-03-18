<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = ['date', 'time', 'duration', 'user_id', 'table_ids', 'guest_count'];

    protected $casts = [
        'table_ids' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
