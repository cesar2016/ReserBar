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

        $userUpper = strtoupper($userMessage);
        
        if (preg_match('/^\s*(SI|SI\s*NO|OK|YES|SI, SI)\s*$/i', $userMessage) && !empty($reservationData)) {
            return response()->json($this->createReservation($reservationData, $userId));
        }
        
        if (preg_match('/^\s*(NO|NOPE)\s*$/i', $userMessage)) {
            return response()->json([
                'response' => 'Entendido. ¿Qué datos quieres cambiar?',
                'reservation_data' => []
            ]);
        }

        $context = $this->contextService->getContext();
        $restaurant = $context['restaurant'];
        $tables = $context['tables'];
        $availability = $context['availability'];

        $tablesList = '';
        foreach ($tables as $table) {
            $tablesList .= "- {$table['nombre']}: capacidad {$table['capacidad']} personas, {$table['ubicacion']}\n";
        }

        $systemPrompt = <<<EOT
Eres el asistente de "ReserBar", restaurante.

REGLAS:
- Solo habla del restaurante
- Responde en español
- Sé breve y directo

HORARIOS DE RESERVA:
- Lunes a Viernes: 10:00 a 24:00
- Sábado: 22:00 a 02:00 (del domingo)
- Domingo: 12:00 a 16:00

RESPUESTAS PARA RESERVAS:
1. Si el usuario quiere reservar Y da fecha, hora y personas → confirma con:
   "Perfecto. Tengo tu reserva:
   📅 Fecha: [DD/MM/AAAA]
   🕐 Hora: [HH:MM]
   👥 Personas: [N]
   ¿Confirmas? SI o NO"

2. Si falta algo → pregunta solo lo que falta

3. Si dice SI → nosotros procesamos la reserva, tú solo confirma que fue exitosa

4. Si dice NO → pregunta qué quiere cambiar

5. Si la hora está fuera del horario de reserva → informa los horarios disponibles

EJEMPLOS:
- "quiero reservar para sabado 21 a las 23 para 9"
→ "Perfecto. Tengo tu reserva:
📅 Fecha: 21/03/2026
🕐 Hora: 23:00
👥 Personas: 9
¿Confirmas? SI o NO"

- "quiero reservar para lunes a las 8 de la mañana"
→ "Lo sentimos, los horarios de reserva son:
- Lunes a Viernes: 10:00 a 24:00
- Sábado: 22:00 a 02:00
- Domingo: 12:00 a 16:00"

HORARIOS:
- Lunes a Viernes: 10:00 a 24:00
- Sábado: 22:00 a 02:00
- Domingo: 12:00 a 16:00
EOT;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userMessage]
                ],
                'temperature' => 0.3,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $aiResponse = $data['choices'][0]['message']['content'] ?? 'Sin respuesta';
                
                $extractedData = $this->extractData($userMessage, $aiResponse);
                
                if (!empty($extractedData)) {
                    $reservationData = array_merge($reservationData, $extractedData);
                }
                
                return response()->json([
                    'response' => $aiResponse,
                    'reservation_data' => $reservationData
                ]);
            }

            return response()->json([
                'error' => $response->json()['error']['message'] ?? 'Error en la API'
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error de conexión: ' . $e->getMessage()
            ], 500);
        }
    }

    private function extractData(string $userMessage, string $aiResponse): array
    {
        $data = [];
        
        $patterns = [
            'date' => '/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/',
            'date2' => '/(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/',
            'time' => '/(\d{1,2}):(\d{2})/',
            'time2' => '/(\d{1,2})\s*(?:hs|:)?\s*(?:de\s*la\s*)?(?:mañana|tarde|noche)/i',
            'people' => '/(\d+)\s*(?:personas?|comensales?|personas)/i',
            'people2' => '/somos\s*(\d+)/i',
        ];
        
        if (preg_match($patterns['date'], $userMessage, $m)) {
            $day = $m[1];
            $month = $m[2];
            $year = strlen($m[3]) == 2 ? '20' . $m[3] : $m[3];
            $data['date'] = "$day/$month/$year";
        } elseif (preg_match($patterns['date2'], $userMessage, $m)) {
            $year = $m[1];
            $month = $m[2];
            $day = $m[3];
            $data['date'] = "$day/$month/$year";
        }
        
        if (preg_match('/(\d{1,2}):(\d{2})/', $userMessage, $m)) {
            $data['time'] = sprintf("%02d:%02d", $m[1], $m[2]);
        } elseif (preg_match('/(\d{1,2})\s*hs?/', $userMessage, $m)) {
            $data['time'] = sprintf("%02d:00", $m[1]);
        } elseif (preg_match('/(\d{1,2})\s*(?:de\s*la\s*)?(mañana|tarde|noche)/i', $userMessage, $m)) {
            $hour = (int)$m[1];
            if ($m[2] === 'tarde' && $hour < 12) $hour += 12;
            if ($m[2] === 'noche' && $hour < 12) $hour += 12;
            if ($m[2] === 'mañana' && $hour === 12) $hour = 0;
            $data['time'] = sprintf("%02d:00", $hour);
        }
        
        if (preg_match('/(\d+)\s*(?:personas?|comensales?)/i', $userMessage, $m)) {
            $data['guest_count'] = (int)$m[1];
        } elseif (preg_match('/somos\s*(\d+)/i', $userMessage, $m)) {
            $data['guest_count'] = (int)$m[1];
        } elseif (preg_match('/para\s*(\d+)/i', $userMessage, $m)) {
            $data['guest_count'] = (int)$m[1];
        }
        
        return $data;
    }

    private function createReservation(array $data, ?int $userId): array
    {
        if (empty($data['date']) || empty($data['time']) || empty($data['guest_count'])) {
            return [
                'error' => 'Faltan datos para la reserva',
                'reservation_data' => $data
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
                'reservation_data' => []
            ];
        }

        return [
            'error' => 'No se pudo crear la reserva: ' . $result['message'],
            'reservation_data' => $data
        ];
    }
}
