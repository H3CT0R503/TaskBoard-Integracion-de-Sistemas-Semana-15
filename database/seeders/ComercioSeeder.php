<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class ComercioSeeder extends Seeder
{
    /**
     * Datos de arranque del proyecto.
     *
     * No son comercios al azar: cada uno cubre un caso que necesito
     * poder probar en pantalla sin inventar registros a mano, y
     * desde esta semana están repartidos entre dos dueños para poder
     * probar también el aislamiento.
     *
     * Comercios de Ana:
     * - Café Amanecer: con transacciones en dos estados distintos, para
     *   ver los badges y los botones de mover estado.
     * - Pupusería Doña Marta: con una transacción Fallida, que es el
     *   único estado desde el que se puede reintentar.
     *
     * Comercios de Carlos:
     * - Ferretería El Tornillo: sin ninguna transacción, para comprobar
     *   que el @empty y el mensaje de "sin actividad" salen bien.
     * - Librería La Página: con una transacción Iniciada, que es la
     *   que uso para probar desde la sesión de Ana que un PATCH a
     *   mano devuelve 404.
     */
    public function run(): void
    {
        // Los traigo por correo y no por id: así el seeder no depende
        // de que Ana haya quedado con el id 1, que es justo la clase
        // de suposición que truena cuando alguien agrega un usuario
        // antes en DatabaseSeeder.
        $ana = User::where('email', 'ana@taskboard.test')->firstOrFail();
        $carlos = User::where('email', 'carlos@taskboard.test')->firstOrFail();

        // create() desde la relación llena el user_id solo. Es la
        // forma correcta de crear un comercio ahora que la columna es
        // obligatoria: con Comercio::create([...]) a secas saldría un
        // "NOT NULL constraint failed: user_id".
        $cafeAmanecer = $ana->comercios()->create([
            'nombre_comercio' => 'Café Amanecer',
            'rubro' => 'Restaurante',
            'telefono' => '2222-1234',
        ]);

        // createMany() desde la relación llena el comercio_id solo,
        // sin que yo tenga que repetir el id en cada arreglo.
        $cafeAmanecer->transacciones()->createMany([
            [
                'monto' => 45.00,
                'cliente_nombre' => 'María López',
                'estado' => 'Completada',
            ],
            [
                'monto' => 12.50,
                'cliente_nombre' => 'Juan Pérez',
                'estado' => 'Iniciada',
            ],
        ]);

        $pupuseria = $ana->comercios()->create([
            'nombre_comercio' => 'Pupusería Doña Marta',
            'rubro' => 'Restaurante',
            'telefono' => '2222-9012',
        ]);

        $pupuseria->transacciones()->create([
            'monto' => 8.00,
            'cliente_nombre' => 'Carlos Ramírez',
            'estado' => 'Fallida',
        ]);

        $carlos->comercios()->create([
            'nombre_comercio' => 'Ferretería El Tornillo',
            'rubro' => 'Ferretería',
            'telefono' => '2222-5678',
        ]);

        $libreria = $carlos->comercios()->create([
            'nombre_comercio' => 'Librería La Página',
            'rubro' => 'Librería',
            'telefono' => '2222-3456',
        ]);

        $libreria->transacciones()->create([
            'monto' => 23.75,
            'cliente_nombre' => 'Sofía Martínez',
            'estado' => 'Iniciada',
        ]);
    }
}
