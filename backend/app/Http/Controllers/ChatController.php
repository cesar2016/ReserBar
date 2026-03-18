<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\RestaurantContextService;

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

        // Confirmar reserva
        if (preg_match('/^\s*(SI|YES|OK|CONFIRMAR)\s*$/i', $userMessage) && !empty($reservationData)) {
            return response()->json($this->createReservation($reservationData, $request->input('user_id')));
        }

        // Cancelar
        if (preg_match('/^\s*(NO|NOPE|CANCELAR|MODIFICAR)\s*$/i', $userMessage)) {
            return response()->json([
                'response' => 'Entendido. Empecemos de nuevo.',
                'reservation_created' => false,
                'reservation_data' => []
            ]);
        }

        // Procesar mensaje
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

        // Si ya tiene todo, no extraer más
        if (isset($data['date']) && isset($data['time']) && isset($data['guest_count'])) {
            return $data;
        }

        // Extraer fecha
        if (!isset($data['date'])) {
            if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $message, $m)) {
                $data['date'] = "{$m[3]}/{$m[2]}/{$m[1]}";
            } elseif (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/', $message, $m)) {
                $day = $m[1];
                $month = $m[2];
                $year = strlen($m[3]) == 2 ? '20' . $m[3] : $m[3];
                $data['date'] = "$day/$month/$year";
            }
        }

        // Extraer hora
        if (!isset($data['time']) && preg_match('/(\d{1,2}):(\d{2})/', $message, $m)) {
            $data['time'] = sprintf("%02d:%02d", (int)$m[1], (int)$m[2]);
        }

        // Extraer personas
        if (!isset($data['guest_count'])) {
            if (preg_match('/(\d+)/', $message, $m)) {
                $count = (int)$m[1];
                if ($count > 0 && $count <= 10) {
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
            return [
                'message' => "Perfecto. Tu reserva:\n📅 Fecha: {$data['date']}\n🕐 Hora: {$data['time']}\n👥 Personas: {$data['guest_count']}\n\n¿Confirmás?"
            ];
        }

        return ['message' => '¿Querés hacer una reserva?'];
    }

    private function createReservation(array $data, ?int $userId): array
    {
        if (empty($data['date']) || empty($data['time']) || empty($data['guest_count'])) {
            return [
                'response' => 'Faltan datos para la reserva.',
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

        if ($result['success']) {
            return [
                'response' => "✅ ¡Reserva confirmada! 🎉\n\n📋 Detalles:\n• Fecha: {$data['date']}\n• Hora: {$data['time']}\n• Personas: {$data['guest_count']}\n\n¡Te esperamos en ReserBar! 🍽️",
                'reservation_created' => true,
                'reservation_id' => $result['id'],
                'reservation_data' => []
            ];
        }

        return [
            'response' => 'No se pudo crear la reserva. Intentá de nuevo.',
            'reservation_created' => false,
            'reservation_data' => $data
        ];
    }
}
