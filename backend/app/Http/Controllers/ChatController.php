<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
        if (preg_match('/^\s*(SI|YES|OK)\s*$/i', $userMessage) && isset($reservationData['ready'])) {
            return response()->json($this->createReservation($reservationData, $request->input('user_id')));
        }

        // Cancelar
        if (preg_match('/^\s*(NO|NOPE|CANCELAR)\s*$/i', $userMessage)) {
            return response()->json([
                'response' => 'Reserva cancelada. ¿Querés iniciar otra? Decime la fecha, hora y cuántas personas.',
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

        // Si ya tiene fecha, hora y personas, no extraer más
        if (isset($data['date']) && isset($data['time']) && isset($data['guest_count'])) {
            return $data;
        }

        // Extraer fecha
        if (!isset($data['date'])) {
            // Buscar fecha completa: DD/MM/AAAA o similar
            if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/', $message, $m)) {
                $day = $m[1];
                $month = $m[2];
                $year = strlen($m[3]) == 2 ? '20' . $m[3] : $m[3];
                $data['date'] = "$day/$month/$year";
            }
        }

        // Extraer hora
        if (!isset($data['time']) && preg_match('/(\d{1,2}):(\d{2})/', $message, $m)) {
            $data['time'] = sprintf("%02d:%02d", (int)$m[1], (int)$m[2]);
        } elseif (!isset($data['time']) && preg_match('/^(\d{1,2})$/', trim($message), $m)) {
            $data['pending_hour'] = (int)$m[1];
        }

        // Extraer personas
        if (!isset($data['guest_count'])) {
            if (preg_match('/(\d+)\s*(?:personas?|comensales?)/i', $message, $m)) {
                $count = (int)$m[1];
                if ($count > 0 && $count <= 10) {
                    $data['guest_count'] = $count;
                }
            } elseif (preg_match('/somos\s*(\d+)/i', $message, $m)) {
                $count = (int)$m[1];
                if ($count > 0 && $count <= 10) {
                    $data['guest_count'] = $count;
                }
            }
        }

        // Verificar si está listo
        if (isset($data['date']) && isset($data['time']) && isset($data['guest_count'])) {
            $data['ready'] = true;
        }

        return $data;
    }

    private function generateResponse(array $data): array
    {
        $hasDate = isset($data['date']);
        $hasTime = isset($data['time']) || isset($data['pending_hour']);
        $hasPeople = isset($data['guest_count']);

        // Si está todo listo
        if ($hasDate && $hasTime && $hasPeople) {
            $time = $data['time'] ?? $data['pending_hour'] . ':00';
            return [
                'message' => "Perfecto. Tu reserva:\n📅 Fecha: {$data['date']}\n🕐 Hora: $time\n👥 Personas: {$data['guest_count']}\n\n¿Confirmás? (SI o NO)"
            ];
        }

        // Si falta algo, preguntar en orden
        if (!$hasDate) {
            return [
                'message' => '¿Para qué fecha querés reservar? (formato: DD/MM/AAAA, ejemplo: 21/03/2026)'
            ];
        }

        if (!$hasTime) {
            return [
                'message' => '¿A qué hora? (formato 24hs, ejemplo: 23:00)'
            ];
        }

        if (!$hasPeople) {
            return [
                'message' => '¿Para cuántas personas? (máximo 10)'
            ];
        }

        return ['message' => '¿Querés hacer una reserva? Decime: fecha (DD/MM/AAAA), hora (HH:MM) y cuántas personas.'];
    }

    private function createReservation(array $data, ?int $userId): array
    {
        $time = $data['time'] ?? ($data['pending_hour'] ?? '20:00') . ':00';

        if (empty($data['date']) || empty($time) || empty($data['guest_count'])) {
            return [
                'response' => 'Faltan datos. Decime: fecha, hora y cuántas personas.',
                'reservation_data' => []
            ];
        }

        $result = $this->contextService->createReservation([
            'user_id' => $userId,
            'name' => 'Usuario',
            'date' => $data['date'],
            'time' => $time,
            'guest_count' => (int) $data['guest_count'],
        ]);

        if ($result['success']) {
            return [
                'response' => "✅ ¡Reserva confirmada! 🎉\n\n📋 Detalles:\n• Fecha: {$data['date']}\n• Hora: $time\n• Personas: {$data['guest_count']}\n\n¡Te esperamos en ReserBar! 🍽️\n\n¿Hay algo más?",
                'reservation_created' => true,
                'reservation_id' => $result['id'],
                'reservation_data' => []
            ];
        }

        return [
            'response' => 'No se pudo crear la reserva. Intentá de nuevo.',
            'reservation_data' => []
        ];
    }
}
