<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\MenuCategory;
use Illuminate\Database\Seeder;

class MenuItemSeeder extends Seeder
{
    public function run(): void
    {
        $categories = MenuCategory::all()->keyBy('slug');

        $items = [
            // Menú del Día
            [
                'category_slug' => 'menu-dia',
                'name' => 'Milanesa de ternera con puré',
                'description' => 'Milanesa de ternera con puré de papas o ensalada mixta',
                'price' => 15500,
                'day_of_week' => 'lunes',
                'ingredients' => ['carne', 'pan rallado', 'papas', 'huevo'],
                'is_featured' => true,
            ],
            [
                'category_slug' => 'menu-dia',
                'name' => 'Milanesa de ternera con puré',
                'description' => 'Milanesa de ternera con puré de papas o ensalada mixta',
                'price' => 15500,
                'day_of_week' => 'martes',
                'ingredients' => ['carne', 'pan rallado', 'papas', 'huevo'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'menu-dia',
                'name' => 'Milanesa de ternera con puré',
                'description' => 'Milanesa de ternera con puré de papas o ensalada mixta',
                'price' => 15500,
                'day_of_week' => 'miercoles',
                'ingredients' => ['carne', 'pan rallado', 'papas', 'huevo'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'menu-dia',
                'name' => 'Milanesa de ternera con puré',
                'description' => 'Milanesa de ternera con puré de papas o ensalada mixta',
                'price' => 15500,
                'day_of_week' => 'jueves',
                'ingredients' => ['carne', 'pan rallado', 'papas', 'huevo'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'menu-dia',
                'name' => 'Milanesa de ternera con puré',
                'description' => 'Milanesa de ternera con puré de papas o ensalada mixta',
                'price' => 15500,
                'day_of_week' => 'viernes',
                'ingredients' => ['carne', 'pan rallado', 'papas', 'huevo'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'menu-dia',
                'name' => 'Lasagna casera de carne',
                'description' => 'Lasagna casera de carne y espinaca con salsa bolognesa',
                'price' => 19500,
                'day_of_week' => 'sabado',
                'ingredients' => ['pasta', 'carne molida', 'espinaca', 'queso', 'tomate'],
                'is_featured' => true,
            ],
            [
                'category_slug' => 'menu-dia',
                'name' => 'Asado de tira con papas',
                'description' => 'Asado de tira con papas fritas y chimichurri',
                'price' => 24000,
                'day_of_week' => 'domingo',
                'ingredients' => ['carne', 'papas', 'chimichurri'],
                'is_featured' => true,
            ],

            // Platos Principales
            [
                'category_slug' => 'principales',
                'name' => 'Ojo de Bife ReserBar',
                'description' => 'Ojo de Bife (400g) con manteca de hierbas y papas rústicas',
                'price' => 35000,
                'ingredients' => ['carne', 'manteca', 'hierbas', 'papas'],
                'is_featured' => true,
            ],
            [
                'category_slug' => 'principales',
                'name' => 'Salmón Rosado a la Plancha',
                'description' => 'Salmón Rosado a la Plancha con vegetales salteados al wok',
                'price' => 32500,
                'ingredients' => ['salmon', 'vegetales', 'wok'],
                'is_featured' => true,
            ],
            [
                'category_slug' => 'principales',
                'name' => 'Sorrentinos de Jamón y Queso',
                'description' => 'Sorrentinos de Jamón y Queso con salsa de hongos',
                'price' => 22000,
                'ingredients' => ['pasta', 'jamon', 'queso', 'hongos', 'crema'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'principales',
                'name' => 'Risotto de Calabaza y Blue Cheese',
                'description' => 'Risotto de Calabaza y Blue Cheese con nueces tostadas',
                'price' => 24500,
                'ingredients' => ['arroz', 'calabaza', 'queso azul', 'nueces'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'principales',
                'name' => 'Pechuga Maryland',
                'description' => 'Pechuga Maryland con banana frita, crema de choclo y papas pay',
                'price' => 21000,
                'ingredients' => ['pollo', 'banana', 'choclo', 'papas'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'principales',
                'name' => 'Cochinillo Confitado',
                'description' => 'Cochinillo Confitado con puré de manzanas y reducción de malbec',
                'price' => 29000,
                'ingredients' => ['cerdo', 'manzanas', 'vino malbec'],
                'is_featured' => true,
            ],
            [
                'category_slug' => 'principales',
                'name' => 'Ravioles de Espinaca y Ricota',
                'description' => 'Ravioles de Espinaca y Ricota con estofado de ternera',
                'price' => 19800,
                'ingredients' => ['pasta', 'espinaca', 'ricota', 'carne'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'principales',
                'name' => 'Parrillada Individual',
                'description' => 'Parrillada Individual: chorizo, morcilla, asado y vacío',
                'price' => 28500,
                'ingredients' => ['chorizo', 'morcilla', 'asado', 'vacío', 'carne'],
                'is_featured' => true,
            ],
            [
                'category_slug' => 'principales',
                'name' => 'Bondiola de Cerdo a la Cerveza Negra',
                'description' => 'Bondiola de Cerdo a la Cerveza Negra con batatas al plomo',
                'price' => 23000,
                'ingredients' => ['cerdo', 'cerveza negra', 'batatas'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'principales',
                'name' => 'Bife de Chorizo Clásico',
                'description' => 'Bife de Chorizo Clásico con huevo frito y morrones asados',
                'price' => 31000,
                'ingredients' => ['carne', 'huevo', 'morrones'],
                'is_featured' => false,
            ],

            // Minutas
            [
                'category_slug' => 'minutas',
                'name' => 'Milanesa Napolitana',
                'description' => 'Milanesa Napolitana con papas fritas',
                'price' => 21000,
                'ingredients' => ['carne', 'pan rallado', 'jamon', 'queso', 'tomate', 'papas'],
                'is_featured' => true,
            ],
            [
                'category_slug' => 'minutas',
                'name' => 'Suprema Suiza',
                'description' => 'Suprema Suiza con salsa blanca y gratén de queso',
                'price' => 19500,
                'ingredients' => ['pollo', 'salsa blanca', 'queso'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'minutas',
                'name' => 'Sándwich de Lomito Completo',
                'description' => 'Sándwich de Lomito Completo: lechuga, tomate, huevo y jamón',
                'price' => 18500,
                'ingredients' => ['carne', 'pan', 'lechuga', 'tomate', 'huevo', 'jamon'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'minutas',
                'name' => 'Hamburguesa La Pampa',
                'description' => 'Hamburguesa La Pampa: doble carne, cheddar, bacon y cebolla caramelizada',
                'price' => 17500,
                'ingredients' => ['carne', 'cheddar', 'bacon', 'cebolla', 'pan'],
                'is_featured' => true,
            ],
            [
                'category_slug' => 'minutas',
                'name' => 'Tortilla de Papas Española',
                'description' => 'Tortilla de Papas Española con chorizo colorado',
                'price' => 16000,
                'ingredients' => ['papas', 'huevo', 'chorizo'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'minutas',
                'name' => 'Revuelto Gramajo Tradicional',
                'description' => 'Revuelto Gramajo Tradicional',
                'price' => 16500,
                'ingredients' => ['huevo', 'jamon', 'cebolla', 'papas'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'minutas',
                'name' => 'Empanadas de Carne (3)',
                'description' => 'Empanadas de Carne Cortada a Cuchillo (3 unidades)',
                'price' => 16200,
                'ingredients' => ['masa', 'carne', 'cebolla', 'aceitunas'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'minutas',
                'name' => 'Choripán Gourmet',
                'description' => 'Choripán Gourmet en pan de campo con salsa criolla',
                'price' => 16800,
                'ingredients' => ['chorizo', 'pan', 'salsa criolla'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'minutas',
                'name' => 'Pizzeta Individual de Muzzarella',
                'description' => 'Pizzeta Individual de Muzzarella',
                'price' => 17000,
                'ingredients' => ['masa', 'queso muzzarella', 'tomate'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'minutas',
                'name' => 'Nuggets de Pollo Caseros',
                'description' => 'Nuggets de Pollo Caseros con barbacoa y fritas',
                'price' => 18000,
                'ingredients' => ['pollo', 'pan rallado', 'papas', 'salsa barbacoa'],
                'is_featured' => false,
            ],

            // Bebidas con Alcohol
            [
                'category_slug' => 'bebidas-alcohol',
                'name' => 'Cerveza Tirada (Pinta)',
                'description' => 'Cerveza Tirada (Pinta)',
                'price' => 6000,
                'ingredients' => ['cerveza'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'bebidas-alcohol',
                'name' => 'Copa de Espumante',
                'description' => 'Copa de Espumante',
                'price' => 7000,
                'ingredients' => ['espumante'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'bebidas-alcohol',
                'name' => 'Vino Tinto Malbec (Copa)',
                'description' => 'Copa de Vino Tinto Malbec',
                'price' => 8000,
                'ingredients' => ['vino', 'malbec'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'bebidas-alcohol',
                'name' => 'Gin Tonic Nacional',
                'description' => 'Gin Tonic Nacional',
                'price' => 8000,
                'ingredients' => ['gin', 'tonica'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'bebidas-alcohol',
                'name' => 'Fernet con Cola (Vaso XL)',
                'description' => 'Fernet con Cola (Vaso XL)',
                'price' => 9500,
                'ingredients' => ['fernet', 'cola'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'bebidas-alcohol',
                'name' => 'Negroni Clásico',
                'description' => 'Negroni Clásico',
                'price' => 9000,
                'ingredients' => ['gin', 'campari', 'vermouth'],
                'is_featured' => false,
            ],

            // Bebidas sin Alcohol
            [
                'category_slug' => 'bebidas-sin-alcohol',
                'name' => 'Gaseosa (500ml)',
                'description' => 'Gaseosa línea Coca-Cola (500ml)',
                'price' => 4500,
                'ingredients' => ['gaseosa'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'bebidas-sin-alcohol',
                'name' => 'Agua Mineral',
                'description' => 'Agua Mineral con o sin gas',
                'price' => 4000,
                'ingredients' => ['agua'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'bebidas-sin-alcohol',
                'name' => 'Limonada con Menta (Jarra)',
                'description' => 'Limonada con Menta y Jengibre (Jarra)',
                'price' => 9000,
                'ingredients' => ['limon', 'menta', 'jengibre', 'agua'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'bebidas-sin-alcohol',
                'name' => 'Jugo de Naranja Exprimido',
                'description' => 'Jugo de Naranja Exprimido',
                'price' => 6500,
                'ingredients' => ['naranja'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'bebidas-sin-alcohol',
                'name' => 'Licuado de Frutilla con Leche',
                'description' => 'Licuado de Frutilla con leche',
                'price' => 8500,
                'ingredients' => ['frutilla', 'leche'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'bebidas-sin-alcohol',
                'name' => 'Té Helado de la Casa',
                'description' => 'Té Helado de la Casa',
                'price' => 5500,
                'ingredients' => ['te'],
                'is_featured' => false,
            ],

            // Postres
            [
                'category_slug' => 'postres',
                'name' => 'Flan Mixto',
                'description' => 'Flan Mixto con dulce de leche y crema',
                'price' => 9000,
                'ingredients' => ['flan', 'dulce de leche', 'crema'],
                'is_featured' => true,
            ],
            [
                'category_slug' => 'postres',
                'name' => 'Panqueques con Dulce de Leche',
                'description' => 'Panqueques con Dulce de Leche (2 unidades)',
                'price' => 10500,
                'ingredients' => ['panqueque', 'dulce de leche'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'postres',
                'name' => 'Tiramisú Artesanal',
                'description' => 'Tiramisú Artesanal con mascarpone y cacao amargo',
                'price' => 13000,
                'ingredients' => ['mascarpone', 'cacao', 'café', 'bizcochuelo'],
                'is_featured' => true,
            ],
            [
                'category_slug' => 'postres',
                'name' => 'Vigilante',
                'description' => 'Queso fresco con dulce de membrillo o batata',
                'price' => 9500,
                'ingredients' => ['queso fresco', 'dulce de membrillo'],
                'is_featured' => false,
            ],
            [
                'category_slug' => 'postres',
                'name' => 'Mousse de Chocolate Intenso',
                'description' => 'Mousse de Chocolate Intenso con frutos rojos',
                'price' => 12000,
                'ingredients' => ['chocolate', 'crema', 'frutos rojos'],
                'is_featured' => false,
            ],
        ];

        foreach ($items as $itemData) {
            $categoryId = $categories[$itemData['category_slug']]->id ?? null;
            
            if (!$categoryId) {
                continue;
            }

            unset($itemData['category_slug']);

            MenuItem::updateOrCreate(
                [
                    'name' => $itemData['name'],
                    'category_id' => $categoryId,
                    'day_of_week' => $itemData['day_of_week'] ?? null,
                ],
                $itemData
            );
        }
    }
}
