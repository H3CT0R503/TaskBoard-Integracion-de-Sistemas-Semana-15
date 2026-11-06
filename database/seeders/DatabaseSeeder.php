<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Punto de entrada de los seeders: lo que corre con
     * php artisan db:seed o con migrate:fresh --seed.
     *
     * Los usuarios se crean acá y no en ComercioSeeder porque ahora
     * son el punto de partida de todo: un comercio sin dueño ya no
     * entra en la tabla, así que los usuarios tienen que existir
     * antes que la primera fila de comercios.
     *
     * Ana y Carlos no son relleno: son las dos sesiones con las que
     * se prueba el aislamiento. Con un solo usuario de prueba no hay
     * forma de comprobar que un 404 sale cuando debe salir.
     *
     * La contraseña va en texto plano en este arreglo y aun así la
     * tabla guarda un hash: el cast 'hashed' del modelo User la
     * cifra al asignarla. Por eso en el seeder no aparece ningún
     * Hash::make().
     */
    public function run(): void
    {
        $ana = User::create([
            'name' => 'Ana López',
            'email' => 'ana@taskboard.test',
            'password' => 'password123',
        ]);

        $carlos = User::create([
            'name' => 'Carlos Pérez',
            'email' => 'carlos@taskboard.test',
            'password' => 'password123',
        ]);

        // El orden del arreglo importa, porque un seeder que dependa
        // de otro necesita que las filas del primero ya existan.
        // ComercioSeeder busca a Ana y a Carlos por correo, así que
        // va después de los dos User::create() de arriba.
        $this->call([
            ComercioSeeder::class,
        ]);

        // Dejo los datos de acceso en pantalla para no tener que
        // volver al archivo cada vez que reinicio la base.
        $this->command->info("Usuarios de prueba (clave: password123):");
        $this->command->info("  {$ana->email} — {$ana->name}");
        $this->command->info("  {$carlos->email} — {$carlos->name}");
    }
}
