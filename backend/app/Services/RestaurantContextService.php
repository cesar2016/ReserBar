<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

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
        $info = [
            'name' => 'ReserBar',
            'description' => 'Restaurante con reservas online',
            'address' => 'Av. Principal 1234, Ciudad',
            'phone' => '+54 11 1234-5678',
            'hours' => [
                'lunes_viernes' => '10:00 - 24:00',
                'sabado' => '22:00 - 02:00 (domingo)',
                'domingo' => '12:00 - 16:00',
            ],
            'max_guests_per_reservation' => 10,
        ];

        try {
            $cached = Redis::get('restaurant_info');
            if ($cached) {
                return json_decode($cached, true);
            }
        } catch (\Exception $e) {
            // Redis no disponible, usar valores por defecto
        }

        return $info;
    }

    private function getTablesInfo(): array
    {
        try {
            $tables = DB::table('tables')
                ->select('id', 'name', 'capacity', 'location')
                ->where('is_active', true)
                ->orWhereNull('is_active')
                ->get()
                ->toArray();

            return array_map(function($table) {
                return [
                    'id' => $table->id,
                    'nombre' => $table->name,
                    'capacidad' => $table->capacity,
                    'ubicacion' => $table->location ?? 'Salón principal',
                ];
            }, $tables);
        } catch (\Exception $e) {
            return [
                ['id' => 1, 'nombre' => 'Mesa 1', 'capacidad' => 4, 'ubicacion' => 'Salón'],
                ['id' => 2, 'nombre' => 'Mesa 2', 'capacidad' => 6, 'ubicacion' => 'Terraza'],
                ['id' => 3, 'nombre' => 'Mesa 3', 'capacidad' => 2, 'ubicacion' => 'Interior'],
            ];
        }
    }

    private function getTodayReservations(): array
    {
        $today = now()->toDateString();

        try {
            $reservations = DB::table('reservations')
                ->whereDate('date', $today)
                ->where('status', '!=', 'cancelled')
                ->select('date', 'time', 'guest_count', 'status')
                ->get()
                ->toArray();

            return $reservations;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getAvailability(): array
    {
        $today = now()->toDateString();
        $todayReservations = $this->getTodayReservations();
        $tables = $this->getTablesInfo();

        $totalCapacity = array_sum(array_column($tables, 'capacidad'));
        $reservedGuests = array_sum(array_column($todayReservations, 'guest_count'));

        return [
            'fecha' => $today,
            'mesas_totales' => count($tables),
            'capacidad_total' => $totalCapacity,
            'reservas_hoy' => count($todayReservations),
            'comensales_reservados' => $reservedGuests,
            'capacidad_disponible' => $totalCapacity - $reservedGuests,
        ];
    }

    public function createReservation(array $data): array
    {
        try {
            $id = DB::table('reservations')->insertGetId([
                'user_id' => $data['user_id'] ?? null,
                'date' => $data['date'],
                'time' => $data['time'],
                'guest_count' => (int) $data['guest_count'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            try {
                Redis::del('reservations_today');
            } catch (\Exception $e) {}

            return ['success' => true, 'id' => $id, 'message' => 'Reserva creada exitosamente'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error al crear reserva: ' . $e->getMessage()];
        }
    }

    public function cancelReservation(int $id): array
    {
        try {
            DB::table('reservations')
                ->where('id', $id)
                ->update(['status' => 'cancelled', 'updated_at' => now()]);

            try {
                Redis::del('reservations_today');
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
                ->where('status', '!=', 'cancelled')
                ->orderBy('date')
                ->orderBy('time')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }
}
