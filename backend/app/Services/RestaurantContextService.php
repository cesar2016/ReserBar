<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class RestaurantContextService
{
    public function getContext(): array
    {
        return [
            'restaurant' => $this->getRestaurantInfo(),
            'tables' => $this->getTablesInfo(),
            'reservations' => $this->getTodayReservations(),
            'availability' => $this->getAvailability(),
        ];
    }

    private function getRestaurantInfo(): array
    {
        return [
            'name' => 'ReserBar',
            'description' => 'Restaurante con reservas online',
            'address' => 'Av. Principal 1234, Ciudad',
            'phone' => '+54 11 1234-5678',
            'hours' => [
                'lunes_viernes' => '10:00 - 00:00',
                'sabado' => '22:00 - 00:00',
                'domingo' => '12:00 - 16:00',
            ],
            'max_guests_per_reservation' => 12,
        ];
    }

    private function getTablesInfo(): array
    {
        try {
            $tables = DB::table('tables')
                ->select('id', 'number', 'capacity', 'location')
                ->get()
                ->toArray();

            return array_map(function($table) {
                return [
                    'id' => $table->id,
                    'nombre' => $table->location . $table->number,
                    'capacidad' => $table->capacity,
                    'ubicacion' => $table->location,
                ];
            }, $tables);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getTodayReservations(): array
    {
        $today = now()->toDateString();

        try {
            $reservations = DB::table('reservations')
                ->whereDate('date', $today)
                ->where(function($q) {
                    $q->whereNull('status')
                      ->orWhere('status', '!=', 'cancelled');
                })
                ->select('id', 'date', 'time', 'duration', 'guest_count', 'table_ids')
                ->get()
                ->toArray();

            return $reservations;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getAvailability(): array
    {
        $tables = $this->getTablesInfo();

        return [
            'mesas_totales' => count($tables),
            'capacidad_total' => array_sum(array_column($tables, 'capacidad')),
        ];
    }

    public function createReservation(array $data): array
    {
        try {
            $date = $data['date'];
            $time = $data['time'];
            $guestCount = (int) $data['guest_count'];

            $tableIds = $this->findAvailableTables($date, $time, $guestCount);
            $userId = $data['user_id'] ?? null;

            if (empty($tableIds)) {
                $alternatives = $this->getAlternativeSuggestions($date, $time, $guestCount);
                return [
                    'success' => false, 
                    'message' => 'No hay disponibilidad suficiente para un grupo de ' . $guestCount . ' personas.',
                    'alternatives' => $alternatives,
                    'tables' => ''
                ];
            }

            $id = DB::table('reservations')->insertGetId([
                'user_id' => $data['user_id'] ?? null,
                'date' => $date,
                'time' => $time,
                'duration' => 120,
                'guest_count' => $guestCount,
                'table_ids' => json_encode($tableIds),
                'status' => 'confirmed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            try {
                Redis::del('reservations:' . now()->format('Y-m'));
            } catch (\Exception $e) {}

            $tableNames = $this->getTableNames($tableIds);

            return [
                'success' => true, 
                'id' => $id, 
                'message' => 'Reserva creada exitosamente',
                'tables' => $tableNames
            ];
        } catch (\Exception $e) {
            return [
                'success' => false, 
                'message' => 'Error al crear reserva: ' . $e->getMessage(),
                'tables' => ''
            ];
        }
    }

    private function findAvailableTables(string $date, string $time, int $guestCount): array
    {
        $allTables = DB::table('tables')->get()->toArray();
        $occupiedTableIds = $this->getOccupiedTableIds($date, $time);

        $freeTables = array_filter($allTables, function($table) use ($occupiedTableIds) {
            return !in_array($table->id, $occupiedTableIds);
        });

        // Lexicographical sort: A1, A2, B1...
        usort($freeTables, function($a, $b) {
            if ($a->location != $b->location) {
                return strcmp($a->location, $b->location);
            }
            return $a->number - $b->number;
        });

        $freeTables = array_values($freeTables);
        $n = count($freeTables);
        
        $bestCombination = [];
        $minWaste = PHP_INT_MAX;

        // Check combinations of 1, 2, and 3 tables
        // 1 table
        for ($i = 0; $i < $n; $i++) {
            $cap = $freeTables[$i]->capacity;
            if ($cap >= $guestCount) {
                $waste = $cap - $guestCount;
                if ($waste < $minWaste) {
                    $minWaste = $waste;
                    $bestCombination = [$freeTables[$i]->id];
                }
            }
        }

        // 2 tables
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $cap = $freeTables[$i]->capacity + $freeTables[$j]->capacity;
                if ($cap >= $guestCount) {
                    $waste = $cap - $guestCount;
                    if ($waste < $minWaste) {
                        $minWaste = $waste;
                        $bestCombination = [$freeTables[$i]->id, $freeTables[$j]->id];
                    }
                }
            }
        }

        // 3 tables
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                for ($k = $j + 1; $k < $n; $k++) {
                    $cap = $freeTables[$i]->capacity + $freeTables[$j]->capacity + $freeTables[$k]->capacity;
                    if ($cap >= $guestCount) {
                        $waste = $cap - $guestCount;
                        if ($waste < $minWaste) {
                            $minWaste = $waste;
                            $bestCombination = [$freeTables[$i]->id, $freeTables[$j]->id, $freeTables[$k]->id];
                        }
                    }
                }
            }
        }

        return $bestCombination;
    }

    private function getOccupiedTableIds(string $date, string $time): array
    {
        $reservations = DB::table('reservations')
            ->where('date', $date)
            ->where(function($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'cancelled');
            })
            ->get();

        $requestedStart = Carbon::parse($time);
        $requestedEnd = $requestedStart->copy()->addMinutes(120);

        $occupiedTableIds = [];
        foreach ($reservations as $res) {
            $resStart = Carbon::parse($res->time);
            $resEnd = $resStart->copy()->addMinutes($res->duration ?? 120);

            if ($requestedStart < $resEnd && $requestedEnd > $resStart) {
                $tableIds = json_decode($res->table_ids, true) ?? [];
                $occupiedTableIds = array_merge($occupiedTableIds, $tableIds);
            }
        }
        return array_unique($occupiedTableIds);
    }

    public function getAlternativeSuggestions(string $date, string $time, int $guestCount): array
    {
        $suggestions = [
            'option_a' => '', 
            'option_b' => '',
            'next_slot' => null // { date, time }
        ];

        // Option A: Max capacity at this time
        $allTables = DB::table('tables')->get()->toArray();
        $occupiedIds = $this->getOccupiedTableIds($date, $time);
        $freeTables = array_filter($allTables, function($t) use ($occupiedIds) { return !in_array($t->id, $occupiedIds); });
        
        usort($freeTables, fn($a, $b) => $b->capacity - $a->capacity);
        $maxCapAtTime = array_sum(array_slice(array_column($freeTables, 'capacity'), 0, 3));
        
        if ($maxCapAtTime > 0) {
            $suggestions['option_a'] = "Para este horario, podemos ofrecerte disponibilidad para grupos de hasta $maxCapAtTime personas.";
        }

        // Option B: Next available slot for the group
        $current = Carbon::parse("$date $time");
        $found = false;
        $todayStr = now()->toDateString();

        for ($i = 1; $i < 144; $i++) {
            $next = $current->copy()->addMinutes($i * 30);
            
            if ($next->hour < 10 && $next->hour >= 0) continue; 

            $tableIds = $this->findAvailableTables($next->toDateString(), $next->toTimeString(), $guestCount);
            if (!empty($tableIds)) {
                $dateLabel = ($next->toDateString() === $todayStr) ? 'Hoy' : $next->format('d/m/Y');
                $suggestions['option_b'] = "Tendríamos disponibilidad para un grupo de $guestCount $dateLabel a las " . $next->format('H:i') . ".";
                $suggestions['next_slot'] = [
                    'date' => $next->toDateString(),
                    'time' => $next->format('H:i'),
                    'label' => "$dateLabel a las " . $next->format('H:i')
                ];
                $found = true;
                break;
            }
        }
        
        return $suggestions;
    }

    private function getTableNames(array $tableIds): string
    {
        if (empty($tableIds)) {
            return '';
        }

        $tables = DB::table('tables')
            ->whereIn('id', $tableIds)
            ->get();

        $names = $tables->map(function($t) {
            return $t->location . $t->number;
        })->toArray();

        return implode(', ', $names);
    }

    public function cancelReservation(int $id): array
    {
        try {
            DB::table('reservations')
                ->where('id', $id)
                ->update(['status' => 'cancelled', 'updated_at' => now()]);

            try {
                Redis::del('reservations:' . now()->format('Y-m'));
            } catch (\Exception $e) {}

            return ['success' => true, 'message' => 'Reserva cancelada'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error al cancelar reserva'];
        }
    }

    public function getReservationsByUser(int $userId): array
    {
        try {
            return DB::table('reservations')
                ->where('user_id', $userId)
                ->whereDate('date', '>=', now()->toDateString())
                ->where(function($q) {
                    $q->whereNull('status')
                      ->orWhere('status', '!=', 'cancelled');
                })
                ->orderBy('date')
                ->orderBy('time')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }
}
