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

        $apiKey = env('GROQ_API_KEY');
        $model = $request->input('model', 'llama-3.1-8b-instant');
        $userId = $request->input('user_id');
        $userMessage = trim($request->input('message'));
        $reservationData = $request->input('reservation_data', []);
        $currentStep = $reservationData['step'] ?? 'inicio';

        $userUpper = strtoupper($userMessage);

        // Confirmar reserva
        if (preg_match('/^\s*(SI|YES|OK|SI,\s*SI|SII)\s*$/i', $userMessage) && isset($reservationData['ready'])) {
            return response()->json($this->createReservation($reservationData, $userId));
        }

        // Cancelar o cambiar
        if (preg_match('/^\s*(NO|NOPE|CAMBIAR|CORREGIR)\s*$/i', $userMessage)) {
            return response()->json([
                'response' => 'Entendido. ¿Qué dato querés cambiar?\n1️⃣ Fecha\n2️⃣ Hora\n3️⃣ Personas',
                'reservation_data' => ['step' => 'cambiar']
            ]);
        }

        // Si dice que quiere cambiar un dato específico
        if ($currentStep === 'cambiar') {
            $reservationData['step'] = 'inicio';
            $reservationData['ready'] = null;
            if (stripos($userMessage, '1') !== false || stripos($userMessage, 'fecha') !== false) {
                unset($reservationData['date']);
            }
            if (stripos($userMessage, '2') !== false || stripos($userMessage, 'hora') !== false) {
                unset($reservationData['time']);
            }
            if (stripos($userMessage, '3') !== false || stripos($userMessage, 'persona') !== false) {
                unset($reservationData['guest_count']);
            }
        }

        // Extraer datos del mensaje del usuario
        $extractedData = $this->extractData($userMessage);
        $reservationData = array_merge($reservationData, $extractedData);

        // Determinar siguiente paso
        $response = $this->determineNextStep($reservationData, $userMessage);
        
        if ($response['done']) {
            $reservationData['step'] = 'confirmar';
            $reservationData['ready'] = true;
        }

        return response()->json([
            'response' => $response['message'],
            'reservation_data' => $reservationData
        ]);
    }

    private function determineNextStep(array $data, string $userMessage): array
    {
        $hasDate = !empty($data['date']);
        $hasTime = !empty($data['time']);
        $hasPeople = !empty($data['guest_count']);

        // Si ya tiene todo, mostrar resumen
        if ($hasDate && $hasTime && $hasPeople) {
            return [
                'done' => true,
                'message' => "Perfecto. Tu reserva:\n📅 Fecha: {$data['date']}\n🕐 Hora: {$data['time']}\n👥 Personas: {$data['guest_count']}\n\n¿Confirmás? (SI o NO)"
            ];
        }

        // Si falta fecha
        if (!$hasDate) {
            if (isset($data['ambiguous'])) {
                return [
                    'done' => false,
                    'message' => $data['ambiguous']
                ];
            }
            return [
                'done' => false,
                'message' => '¿Para qué fecha querés reservar? (ej: 21/03/2026)'
            ];
        }

        // Si falta hora
        if (!$hasTime) {
            if (isset($data['ambiguous'])) {
                return [
                    'done' => false,
                    'message' => $data['ambiguous']
                ];
            }
            return [
                'done' => false,
                'message' => '¿A qué hora querés venir? (ej: 23:00)'
            ];
        }

        // Si falta personas
        if (!$hasPeople) {
            if (isset($data['ambiguous'])) {
                return [
                    'done' => false,
                    'message' => $data['ambiguous']
                ];
            }
            return [
                'done' => false,
                'message' => '¿Para cuántas personas?'
            ];
        }

        return [
            'done' => false,
            'message' => '¿Querés hacer una reserva?'
        ];
    }

    private function extractData(string $message): array
    {
        $data = [];
        $message = trim($message);

        // Detectar si quiere hacer reserva
        if (preg_match('/(reserva|reservar|reservame|quiero\s+ir|quiero\s+ir\s+a\s+cenar|ir\s+a\s+cenar)/i', $message)) {
            $data['wants_reservation'] = true;
        }

        // Extraer fecha
        $datePatterns = [
            '/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/',
            '/(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/',
        ];

        foreach ($datePatterns as $pattern) {
            if (preg_match($pattern, $message, $m)) {
                if (count($m) === 4) {
                    if (strlen($m[1]) === 4) {
                        $day = $m[3];
                        $month = $m[2];
                        $year = $m[1];
                    } else {
                        $day = $m[1];
                        $month = $m[2];
                        $year = strlen($m[3]) == 2 ? '20' . $m[3] : $m[3];
                    }
                    $data['date'] = "$day/$month/$year";
                    break;
                }
            }
        }

        // Detectar día de la semana
        $dayOfWeek = $this->detectDayOfWeek($message);
        if ($dayOfWeek && !isset($data['date'])) {
            $data['day_of_week'] = $dayOfWeek;
            $data['ambiguous'] = "Perfecto. El próximo $dayOfWeek. ¿Qué fecha específica? (ej: 21/03)";
        }

        // Extraer hora
        if (preg_match('/(\d{1,2}):(\d{2})/', $message, $m)) {
            $hour = (int)$m[1];
            $minute = $m[2];
            if ($hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59) {
                $data['time'] = sprintf("%02d:%02d", $hour, $minute);
            }
        } elseif (preg_match('/(\d{1,2})\s*hs?/', $message, $m)) {
            $data['time'] = sprintf("%02d:00", (int)$m[1]);
        } elseif (preg_match('/(\d{1,2})\s*(?:de\s*la\s*)?(mañana|tarde|noche)/i', $message, $m)) {
            $hour = (int)$m[1];
            $period = strtolower($m[2]);
            
            if ($period === 'mañana') {
                $hour = $hour === 12 ? 0 : $hour;
            } elseif ($period === 'tarde') {
                $hour = $hour < 12 ? $hour + 12 : $hour;
            } elseif ($period === 'noche') {
                $hour = $hour < 12 ? $hour + 12 : $hour;
            }
            
            $data['time'] = sprintf("%02d:00", $hour);
            $data['ambiguous'] = "{$m[1]} {$m[2]} = {$data['time']}. ¿Confirmás esta hora?";
        } elseif (preg_match('/^(2[0-3]|[01]?[0-9])$/', trim($message), $m)) {
            $hour = (int)$m[1];
            $data['ambiguous'] = "¿$hour:00 o $hour:30?";
        }

        // Extraer cantidad de personas
        if (preg_match('/(\d+)\s*(?:personas?|comensales?|pax)/i', $message, $m)) {
            $count = (int)$m[1];
            if ($count > 0 && $count <= 20) {
                $data['guest_count'] = $count;
            }
        } elseif (preg_match('/somos\s*(\d+)/i', $message, $m)) {
            $data['guest_count'] = (int)$m[1];
        } elseif (preg_match('/(?:para|mesa\s*para)\s*(\d+)/i', $message, $m)) {
            $data['guest_count'] = (int)$m[1];
        }

        // Validar límite de personas
        if (isset($data['guest_count']) && $data['guest_count'] > 10) {
            $data['ambiguous'] = "El máximo es 10 personas por reserva. ¿Para cuántas personas querés?";
            unset($data['guest_count']);
        }

        return $data;
    }

    private function detectDayOfWeek(string $message): ?string
    {
        $days = [
            'lunes' => 'lunes',
            'martes' => 'martes',
            'miercoles' => 'miércoles',
            'miércoles' => 'miércoles',
            'jueves' => 'jueves',
            'viernes' => 'viernes',
            'sabado' => 'sábado',
            'sábado' => 'sábado',
            'domingo' => 'domingo',
            'hoy' => 'hoy',
            'mañana' => 'mañana',
        ];

        foreach ($days as $key => $day) {
            if (preg_match("/\\b$key\\b/i", $message)) {
                return $day;
            }
        }

        return null;
    }

    private function createReservation(array $data, ?int $userId): array
    {
        if (empty($data['date']) || empty($data['time']) || empty($data['guest_count'])) {
            return [
                'response' => 'Faltan datos para la reserva. ¿Querés empezar de nuevo?',
                'reservation_data' => ['step' => 'inicio']
            ];
        }

        $result = $this->contextService->createReservation([
            'user_id' => $userId,
            'name' => 'Usuario',
            'date' => $data['date'],
            'time' => $data['time'],
            'guest_count' => (int) $data['guest_count'],
        ]);

        if ($result['success']) {
            return [
                'response' => "✅ ¡Reserva confirmada! 🎉\n\n📋 Detalles:\n• Fecha: {$data['date']}\n• Hora: {$data['time']}\n• Personas: {$data['guest_count']}\n\n¡Te esperamos en ReserBar! 🍽️\n\n¿Hay algo más en lo que pueda ayudarte?",
                'reservation_created' => true,
                'reservation_id' => $result['id'],
                'reservation_data' => ['step' => 'completado']
            ];
        }

        return [
            'response' => 'No se pudo crear la reserva. Intentá de nuevo.',
            'reservation_data' => ['step' => 'inicio']
        ];
    }
}
