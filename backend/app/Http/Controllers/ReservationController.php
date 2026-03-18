<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Table;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class ReservationController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'guest_count' => 'required|integer|min:1|max:12',
        ]);

        $date = $request->date;
        $time = $request->time;
        $guestCount = $request->guest_count;

        $carbonDate = Carbon::parse($date);
        $carbonTime = Carbon::parse($time);
        $dayOfWeek = $carbonDate->dayOfWeek;

        // Check schedule
        if (!$this->checkSchedule($dayOfWeek, $carbonTime)) {
            return response()->json(['message' => 'Está reservando fuera de horario.'], 400);
        }

        // Find available tables
        $availableTables = $this->findAvailableTables($date, $time, $guestCount);

        if (empty($availableTables)) {
            return response()->json(['message' => 'No hay mesas disponibles para esa fecha y horario.'], 400);
        }

        // Sort tables: A1, A2... B1, B2... (array of arrays)
        usort($availableTables, function ($a, $b) {
            if ($a['location'] === $b['location']) {
                return $a['number'] - $b['number'];
            }
            return strcmp($a['location'], $b['location']);
        });

        $tableIds = array_column($availableTables, 'id');

        $reservation = Reservation::create([
            'date' => $date,
            'time' => $time,
            'duration' => 120,
            'user_id' => $request->user()->id,
            'table_ids' => $tableIds,
            'guest_count' => $guestCount,
        ]);

        // Clear cache
        $this->clearReservationCache();

        return response()->json($reservation, 201);
    }

    private function findAvailableTables($date, $time, $guestCount)
    {
        // 1. Get all tables
        $allTables = Table::all()->toArray();
        
        // 2. Get reservations for this date
        $reservations = Reservation::where('date', $date)->get();

        // 3. Identify occupied table IDs for the requested time slot (2 hours)
        $requestedStart = Carbon::parse($time);
        $requestedEnd = $requestedStart->copy()->addMinutes(120);

        $occupiedTableIds = [];

        foreach ($reservations as $res) {
            $resStart = Carbon::parse($res->time);
            $resEnd = $resStart->copy()->addMinutes($res->duration);

            // Check overlap
            if ($requestedStart < $resEnd && $requestedEnd > $resStart) {
                // If overlap, add all tables of this reservation to occupied list
                $occupiedTableIds = array_merge($occupiedTableIds, $res->table_ids);
            }
        }
        $occupiedTableIds = array_unique($occupiedTableIds);

        // 4. Filter available tables
        $freeTables = array_filter($allTables, function ($table) use ($occupiedTableIds) {
            return !in_array($table['id'], $occupiedTableIds);
        });

        // 5. Sorting for Correlative Order: A1, A2, B1...
        usort($freeTables, function ($a, $b) {
            if ($a['location'] === $b['location']) {
                return $a['number'] - $b['number'];
            }
            return strcmp($a['location'], $b['location']);
        });

        $selectedTables = [];
        $currentCapacity = 0;

        foreach ($freeTables as $table) {
            $selectedTables[] = $table;
            $currentCapacity += $table['capacity'];
            
            if ($currentCapacity >= $guestCount) {
                break;
            }
        }

        if ($currentCapacity < $guestCount) {
            return []; // Not enough capacity found
        }

        return $selectedTables;
    }

    public function update(Request $request, Reservation $reservation)
    {
        $reservation->update($request->all());
        
        // Clear cache
        $this->clearReservationCache();

        return response()->json($reservation);
    }

    public function destroy(Reservation $reservation)
    {
        $reservation->delete();

        // Clear cache
        $this->clearReservationCache();

        return response()->json(['message' => 'Reservation deleted']);
    }

    private function clearReservationCache()
    {
        // Clear current month cache
        $currentMonth = Carbon::now()->format('Y-m');
        Redis::del('reservations:' . $currentMonth);
    }

    public function index(Request $request)
    {
        // Generate cache key based on current month (YYYY-MM)
        $cacheKey = 'reservations:' . Carbon::now()->format('Y-m');

        // Try to get from Redis
        $cachedData = Redis::get($cacheKey);

        if ($cachedData) {
            return response()->json(json_decode($cachedData));
        }

        // If not in cache, fetch from DB
        $query = Reservation::with('user:id,name,email');

        if ($request->has('date')) {
            $query->where('date', $request->query('date'));
        }

        $reservations = $query->orderBy('date')->orderBy('time')->get();

        // Store in Redis for 30 days (or until invalidated)
        Redis::set($cacheKey, $reservations->toJson(), 'EX', 60 * 60 * 24 * 30);

        return response()->json($reservations);
    }

    private function checkSchedule($dayOfWeek, $time) {
        $isOpen = false;
        if ($dayOfWeek >= 1 && $dayOfWeek <= 5) { // Mon-Fri
            if ($time->hour >= 10 && $time->hour < 24) $isOpen = true;
        } elseif ($dayOfWeek == 6) { // Saturday
            if ($time->hour >= 22) $isOpen = true;
        } elseif ($dayOfWeek == 0) { // Sunday
            if ($time->hour < 2 || ($time->hour >= 12 && $time->hour < 16)) $isOpen = true;
        }
        return $isOpen;
    }
}
