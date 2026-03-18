<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Table extends Model
{
    protected $fillable = ['location', 'number', 'capacity'];

    public function reservations()
    {
        return $this->belongsToMany(Reservation::class, 'reservation_table'); // Pivot table if needed, but Reservation has table_ids JSON.
        // Actually, if I store table_ids in Reservation, I don't strictly need a many-to-many relationship on Table unless I want to query it.
        // But let's stick to the JSON column approach for now as defined in migration.
        // However, Eloquent might not automatically resolve it.
    }
}
