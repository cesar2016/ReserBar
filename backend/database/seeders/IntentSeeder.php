<?php

namespace Database\Seeders;

use App\Models\Intent;
use Illuminate\Database\Seeder;

class IntentSeeder extends Seeder
{
    public function run(): void
    {
        $intents = [
            [
                'name' => 'greeting',
                'description' => 'Saludo inicial del usuario',
                'required_entities' => null,
                'response_template' => '¡Hola! Soy tu asistente de ReserBar. ¿En qué puedo ayudarte?',
                'example_phrases' => [
                    'hola',
                    'buenos días',
                    'buenas tardes',
                    'buenas noches',
                    'que tal',
                    'buen día',
                    'hola como estás',
                    'hey',
                    'saludos',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'make_reservation',
                'description' => 'El usuario quiere hacer una reserva de mesa',
                'required_entities' => ['date', 'time', 'guest_count'],
                'response_template' => 'Perfecto, voy a registrar tu reserva.',
                'example_phrases' => [
                    'quiero hacer una reserva',
                    'reservar mesa',
                    'necesito una mesa',
                    'hacer una reserva para mañana',
                    'reserva para dos personas',
                    'quiero reservar para el viernes a las 9',
                    'hola quiero hacer una reserva para el 26 de este mes a las 9 de la noche somos 7 personas',
                    'buenos días necesito reservar una mesa para hoy',
                    'quería reservar para cenar',
                    'somos 4 queremos reservar para el sábado',
                    'quiero ir al restaurante mañana',
                    'necesito mesa para 6 personas',
                    'hacer reserva',
                    'reservar',
                    'pedir una mesa',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'cancel_reservation',
                'description' => 'El usuario quiere cancelar una reserva existente',
                'required_entities' => [],
                'response_template' => 'Voy a cancelar tu reserva.',
                'example_phrases' => [
                    'cancelar reserva',
                    'anular reserva',
                    'ya no puedo ir',
                    'cambiar fecha',
                    'eliminar mi reserva',
                    'no voy a poder',
                    'cancelar',
                    'anular',
                    'no voy a ir',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'menu_query',
                'description' => 'El usuario quiere ver el menú o carta del restaurante',
                'required_entities' => [],
                'response_template' => 'Aquí está nuestro menú.',
                'example_phrases' => [
                    'ver el menú',
                    'que tienen para comer',
                    'carta del restaurante',
                    'platos disponibles',
                    'que comida tienen',
                    'ver carta',
                    'menú del día',
                    'que me recomiendas',
                    'que hay de plato principal',
                    'postres disponibles',
                    'quiero ver el menú completo',
                    'bebidas',
                    'ver las opciones',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'query_reservation',
                'description' => 'El usuario quiere consultar sus reservas existentes',
                'required_entities' => [],
                'response_template' => 'Aquí están tus reservas.',
                'example_phrases' => [
                    'mis reservas',
                    'ver mis reservas',
                    'cuando tengo reserva',
                    'mis turnos',
                    'reservas pendientes',
                    'tengo alguna reserva',
                    'ver reservas',
                    'mis citas',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'query_availability',
                'description' => 'El usuario quiere saber si hay disponibilidad',
                'required_entities' => ['date', 'time', 'guest_count'],
                'response_template' => 'Consultando disponibilidad...',
                'example_phrases' => [
                    'hay disponibilidad',
                    'esta libre',
                    'tienen mesas',
                    'puedo reservar',
                    'hay lugar',
                    'esta disponible',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'query_hours',
                'description' => 'El usuario pregunta por los horarios del restaurante',
                'required_entities' => [],
                'response_template' => 'Nuestros horarios son...',
                'example_phrases' => [
                    'horarios',
                    'a qué hora abren',
                    'hasta qué hora atiende',
                    'horario de atención',
                    'cuales son los horarios',
                    'a qué hora cierran',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'query_location',
                'description' => 'El usuario pregunta por la ubicación del restaurante',
                'required_entities' => [],
                'response_template' => 'Estamos ubicados en...',
                'example_phrases' => [
                    'ubicación',
                    'donde están',
                    'dirección',
                    'como llego',
                    'donde queda',
                    'dirección del restaurante',
                    'ubicado',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'unknown',
                'description' => 'No se pudo determinar la intención del usuario',
                'required_entities' => null,
                'response_template' => 'No estoy seguro de entender. ¿Podrías reformular?',
                'example_phrases' => [
                    'asdfgh',
                    '???',
                    'no sé',
                    'tal vez',
                ],
                'is_active' => true,
            ],
        ];

        foreach ($intents as $intentData) {
            Intent::updateOrCreate(
                ['name' => $intentData['name']],
                $intentData
            );
        }
    }
}
