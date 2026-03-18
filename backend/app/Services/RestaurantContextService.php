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
            'max_guests_per_reservation' => 8,
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

            if (empty($tableIds)) {
                return [
                    'success' => false, 
                    'message' => 'No hay mesas disponibles para esa fecha y horario.',
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
        $allTables = DB::table('tables')
            ->select('id', 'number', 'capacity', 'location')
            ->orderBy('location')
            ->orderBy('number')
            ->get()
            ->toArray();

        if (empty($allTables)) {
            return [];
        }

        $reservations = DB::table('reservations')
            ->where('date', $date)
            ->where(function($q) {
                $q->whereNull('status')
                  ->orWhere('status', '!=', 'cancelled');
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

        $occupiedTableIds = array_unique($occupiedTableIds);

        $freeTables = array_filter($allTables, function($table) use ($occupiedTableIds) {
            return !in_array($table->id, $occupiedTableIds);
        });

        $freeTables = array_values($freeTables);

        $selectedTables = [];
        $currentCapacity = 0;

        foreach ($freeTables as $table) {
            $selectedTables[] = $table->id;
            $currentCapacity += $table->capacity;
            
            if ($currentCapacity >= $guestCount) {
                break;
            }
        }

        if ($currentCapacity < $guestCount) {
            return [];
        }

        return $selectedTables;
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
