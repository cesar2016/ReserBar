<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\RestaurantContextService;
use Carbon\Carbon;

class ChatController extends Controller
{
    private RestaurantContextService $contextService;

    public function __construct(RestaurantContextService $contextService)
    {
        $this->contextService = $contextService;
    }

    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'model' => 'nullable|string',
            'user_id' => 'nullable|integer',
        ]);

        $userMessage = trim($request->input('message'));
        $reservationData = $request->input('reservation_data', []);

        $userUpper = strtoupper($userMessage);

        if (isset($reservationData['ready']) && $reservationData['ready']) {
            return response()->json($this->createReservation($reservationData, $request->input('user_id')));
        }

        if (preg_match('/^\s*(SI|YES|OK|CONFIRMAR)\s*$/i', $userMessage) && !empty($reservationData)) {
            return response()->json($this->createReservation($reservationData, $request->input('user_id')));
        }

        if (preg_match('/^\s*(NO|NOPE|CANCELAR|MODIFICAR)\s*$/i', $userMessage)) {
            return response()->json([
                'response' => 'Entendido. Empecemos de nuevo.',
                'reservation_created' => false,
                'reservation_data' => []
            ]);
        }

        $extractedData = $this->extractAndProcess($userMessage, $reservationData);
        $response = $this->generateResponse($extractedData);

        return response()->json([
            'response' => $response['message'],
            'reservation_data' => $extractedData
        ]);
    }

    private function extractAndProcess(string $message, array $data): array
    {
        $message = strtolower(trim($message));

        if (isset($data['date']) && isset($data['time']) && isset($data['guest_count'])) {
            return $data;
        }

        if (!isset($data['date'])) {
            if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $message, $m)) {
                $data['date'] = "{$m[1]}-{$m[2]}-{$m[3]}";
            } elseif (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/', $message, $m)) {
                $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                $month = str_pad($m[2], 2, '0', STR_PAD_LEFT);
                $year = strlen($m[3]) == 2 ? '20' . $m[3] : $m[3];
                $data['date'] = "$year-$month-$day";
                $data['dateDisplay'] = "$day/$month/$year";
            }
        }

        if (!isset($data['time']) && preg_match('/(\d{1,2}):(\d{2})/', $message, $m)) {
            $data['time'] = sprintf("%02d:%02d", (int)$m[1], (int)$m[2]);
        }

        if (!isset($data['guest_count'])) {
            if (preg_match('/(\d+)/', $message, $m)) {
                $count = (int)$m[1];
                if ($count > 0 && $count <= 8) {
                    $data['guest_count'] = $count;
                }
            }
        }

        return $data;
    }

    private function generateResponse(array $data): array
    {
        $hasDate = isset($data['date']);
        $hasTime = isset($data['time']);
        $hasPeople = isset($data['guest_count']);

        if ($hasDate && $hasTime && $hasPeople) {
            $dateDisplay = $data['dateDisplay'] ?? $this->formatDateForDisplay($data['date']);
            return [
                'message' => "Perfecto. Tu reserva:\n📅 Fecha: {$dateDisplay}\n🕐 Hora: {$data['time']}\n👥 Personas: {$data['guest_count']}\n\n¿Confirmás? (Responde SI o NO)"
            ];
        }

        $missing = [];
        if (!$hasDate) $missing[] = '📅 fecha';
        if (!$hasTime) $missing[] = '🕐 hora';
        if (!$hasPeople) $missing[] = '👥 cantidad de personas';

        return [
            'message' => "Para hacer la reserva necesito:\n" . implode("\n", $missing) . "\n\n¿Querés hacer una reserva?"
        ];
    }

    private function formatDateForDisplay(string $date): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
            return "{$m[3]}/{$m[2]}/{$m[1]}";
        }
        return $date;
    }

    private function createReservation(array $data, ?int $userId): array
    {
        if (empty($data['date']) || empty($data['time']) || empty($data['guest_count'])) {
            return [
                'response' => '❌ Faltan datos para la reserva.',
                'reservation_created' => false,
                'reservation_data' => []
            ];
        }

        $result = $this->contextService->createReservation([
            'user_id' => $userId,
            'date' => $data['date'],
            'time' => $data['time'],
            'guest_count' => (int) $data['guest_count'],
        ]);

        $dateDisplay = $this->formatDateForDisplay($data['date']);

        if ($result['success']) {
            return [
                'response' => "✅ ¡Reserva confirmada! 🎉\n\n📋 Detalles:\n• Fecha: {$dateDisplay}\n• Hora: {$data['time']}\n• Personas: {$data['guest_count']}\n• Mesa: {$result['tables']}\n\n¡Te esperamos en ReserBar! 🍽️",
                'reservation_created' => true,
                'reservation_id' => $result['id'],
                'reservation_data' => []
            ];
        }

        return [
            'response' => "❌ No se pudo crear la reserva.\n\n{$result['message']}\n\n¿Querés intentar con otro horario o fecha?",
            'reservation_created' => false,
            'reservation_data' => $data
        ];
    }
}
