<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\RestaurantContextService;
use App\Services\MenuService;
use App\Services\IntentEmbeddingService;
use App\Services\ConversationContextService;
use Carbon\Carbon;

class ChatController extends Controller
{
    private RestaurantContextService $contextService;
    private MenuService $menuService;
    private IntentEmbeddingService $intentService;
    private ConversationContextService $conversationService;

    public function __construct(
        RestaurantContextService $contextService,
        MenuService $menuService,
        IntentEmbeddingService $intentService,
        ConversationContextService $conversationService
    ) {
        $this->contextService = $contextService;
        $this->menuService = $menuService;
        $this->intentService = $intentService;
        $this->conversationService = $conversationService;
    }

    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'model' => 'nullable|string',
            'user_id' => 'nullable|integer',
            'session_id' => 'nullable|string',
        ]);

        $userMessage = trim($request->input('message'));
        $userId = $request->input('user_id');
        $sessionId = $request->input('session_id') ?? $this->conversationService->getOrCreateSession($userId);
        
        $userUpper = strtoupper($userMessage);
        
        if (preg_match('/^\s*(SI|YES|OK|CONFIRMAR|AFIRMATIVO)\s*$/i', $userMessage)) {
            return $this->handleConfirmation($userId, $sessionId);
        }
        
        if (preg_match('/^\s*(NO|NOPE|CANCELAR|MODIFICAR)\s*$/i', $userMessage)) {
            return $this->handleCancellation($userId, $sessionId);
        }

        $result = $this->conversationService->processMessage($userMessage, $userId, $sessionId);
        $context = $result['context'];
        $intent = $result['intent'];
        $entities = $result['entities'];
        
        $response = $this->generateResponse($userMessage, $context, $intent, $entities, $userId, $sessionId);
        
        $this->conversationService->addToHistory($userId, $sessionId, 'user', $userMessage);
        $this->conversationService->addToHistory($userId, $sessionId, 'assistant', $response['message']);
        
        $this->intentService->saveConversation(
            $userId,
            $userMessage,
            $response['message'],
            $intent,
            $entities,
            $context['intent_confidence'] ?? 0.5,
            $sessionId
        );
        
        return response()->json([
            'response' => $response['message'],
            'intent' => $intent?->name,
            'entities' => $context['entities'],
            'session_id' => $sessionId,
            'ready' => $this->conversationService->isReadyForConfirmation($context),
        ]);
    }

    private function handleConfirmation(?int $userId, string $sessionId): \Illuminate\Http\JsonResponse
    {
        $context = $this->conversationService->getContext($userId, $sessionId);
        
        if (!$this->conversationService->isReadyForConfirmation($context)) {
            return response()->json([
                'response' => 'No tengo todos los datos necesarios para confirmar. ¿Querés empezar de nuevo?',
                'intent' => $context['intent'],
                'entities' => $context['entities'],
                'session_id' => $sessionId,
                'ready' => false,
            ]);
        }
        
        $entities = $context['entities'];
        
        $result = $this->contextService->createReservation([
            'user_id' => $userId,
            'date' => $entities['date'],
            'time' => $entities['time'],
            'guest_count' => (int) $entities['guest_count'],
        ]);
        
        if ($result['success']) {
            $this->conversationService->clearContext($userId, $sessionId);
            
            return response()->json([
                'response' => "✅ ¡Reserva confirmada! 🎉\n\n📋 Detalles:\n• Fecha: {$this->formatDateForDisplay($entities['date'])}\n• Hora: {$entities['time']}\n• Personas: {$entities['guest_count']}\n• Mesa: {$result['tables']}\n\n¡Te esperamos en ReserBar! 🍽️",
                'intent' => 'make_reservation',
                'entities' => [],
                'session_id' => $sessionId,
                'ready' => false,
                'reservation_created' => true,
            ]);
        }
        
        return response()->json([
            'response' => "❌ No se pudo crear la reserva.\n\n{$result['message']}\n\n¿Querés intentar con otro horario o fecha?",
            'intent' => 'make_reservation',
            'entities' => $context['entities'],
            'session_id' => $sessionId,
            'ready' => false,
        ]);
    }

    private function handleCancellation(?int $userId, string $sessionId): \Illuminate\Http\JsonResponse
    {
        $this->conversationService->clearContext($userId, $sessionId);
        
        return response()->json([
            'response' => 'Entendido. Empecemos de nuevo. 😊\n\n¿En qué puedo ayudarte?',
            'intent' => null,
            'entities' => [],
            'session_id' => $sessionId,
            'ready' => false,
        ]);
    }

    private function generateResponse(
        string $message,
        array $context,
        $intent,
        array $entities,
        ?int $userId,
        string $sessionId
    ): array {
        $step = $context['step'];
        
        if ($intent && $intent->name === 'greeting') {
            return [
                'message' => "¡Hola! 👋 Soy el asistente de ReserBar.\n\nPuedo ayudarte a:\n📅 Hacer una reserva\n🍽️ Ver nuestro menú\n🕐 Conocer los horarios\n📍 Ubicación del restaurante\n\n¿En qué puedo ayudarte?",
            ];
        }
        
        if ($intent && $intent->name === 'menu_query') {
            return $this->handleMenuQuery($context, $sessionId);
        }
        
        if ($intent && $intent->name === 'make_reservation') {
            return $this->handleReservationFlow($context, $sessionId);
        }
        
        if ($intent && $intent->name === 'cancel_reservation') {
            return [
                'message' => 'Para cancelar una reserva necesito saber cuál es. ¿Podrías darme el número de confirmación o la fecha de tu reserva?',
            ];
        }
        
        if ($intent && $intent->name === 'query_reservation') {
            return $this->handleQueryReservations($userId);
        }
        
        $missing = $this->conversationService->getMissingEntities($context);
        if (!empty($missing)) {
            return $this->askForMissingEntity($context, $missing[0]);
        }
        
        return [
            'message' => "No estoy seguro de entender. 😊\n\n¿Querés hacer una reserva? Decime:\n📅 ¿Para qué fecha?\n🕐 ¿A qué hora?\n👥 ¿Cuántas personas?",
        ];
    }

    private function handleMenuQuery(array $context, string $sessionId): array
    {
        $menu = $this->menuService->getFullMenu();
        
        if (empty($menu)) {
            return ['message' => 'Por el momento no tenemos menú disponible. 😊'];
        }
        
        $menuText = "📋 Nuestra Carta:\n\n";
        
        foreach ($menu as $category) {
            $menuText .= "{$category['icon']} {$category['name']}:\n";
            foreach (array_slice($category['items'], 0, 4) as $item) {
                $menuText .= "   • {$item['name']} - {$item['formatted_price']}\n";
            }
            if (count($category['items']) > 4) {
                $menuText .= "   ... y " . (count($category['items']) - 4) . " más\n";
            }
            $menuText .= "\n";
        }
        
        $menuText .= "¿Querés que te recomiende algo o hacés una reserva? 😊";
        
        return ['message' => $menuText];
    }

    private function handleReservationFlow(array $context, string $sessionId): array
    {
        if ($this->conversationService->isReadyForConfirmation($context)) {
            $entities = $context['entities'];
            $dateDisplay = $this->formatDateForDisplay($entities['date']);
            
            return [
                'message' => "Perfecto. Tu reserva:\n📅 {$dateDisplay}\n🕐 {$entities['time']}\n👥 {$entities['guest_count']} personas\n\n¿Confirmás? (Responde SI o NO)"
            ];
        }
        
        $missing = $this->conversationService->getMissingEntities($context);
        
        if (in_array('date', $missing)) {
            return ['message' => "📅 ¿Para qué fecha querés hacer la reserva?"];
        }
        
        if (in_array('time', $missing)) {
            return ['message' => "🕐 ¿A qué hora querés reservar?\n(Lunes a Viernes: 10:00 a 00:00)"];
        }
        
        if (in_array('guest_count', $missing)) {
            return ['message' => "👥 ¿Para cuántas personas?\n(Mínimo 1 - Máximo 8)"];
        }
        
        return ['message' => "¿Querés hacer una reserva? 😊"];
    }

    private function askForMissingEntity(array $context, string $missing): array
    {
        $messages = [
            'date' => "📅 ¿Para qué fecha? (Ej: 26 de marzo, mañana, próximo viernes)",
            'time' => "🕐 ¿A qué hora? (Ej: 21:00, 9pm)",
            'guest_count' => "👥 ¿Para cuántas personas? (1 a 8)",
        ];
        
        return ['message' => $messages[$missing] ?? "Necesito más información."];
    }

    private function handleQueryReservations(?int $userId): array
    {
        if (!$userId) {
            return ['message' => 'Necesitas estar logueado para ver tus reservas. 😊'];
        }
        
        $reservations = $this->contextService->getReservationsByUser($userId);
        
        if (empty($reservations)) {
            return ['message' => 'No tenés reservas pendientes. 😊\n\n¿Querés hacer una nueva reserva?'];
        }
        
        $text = "📋 Tus reservas:\n\n";
        foreach ($reservations as $res) {
            $text .= "• {$this->formatDateForDisplay($res->date)} a las {$res->time}\n";
            $text .= "  {$res->guest_count} personas\n\n";
        }
        
        return ['message' => $text . "¿Querés hacer algo más?"];
    }

    private function formatDateForDisplay(string $date): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
            return "{$m[3]}/{$m[2]}/{$m[1]}";
        }
        return $date;
    }
}
