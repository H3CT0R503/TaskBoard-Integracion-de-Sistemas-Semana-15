<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla raíz del sistema: cada fila es un comercio afiliado a la
     * pasarela. Se crea primero porque las transacciones apuntan acá,
     * y una clave foránea no puede referenciar una tabla que no existe
     * todavía; de ahí que el nombre del archivo lleve la fecha menor.
     */
    public function up(): void
    {
        Schema::create('comercios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_comercio');
            $table->string('rubro');
            // El teléfono es opcional: un comercio puede afiliarse sin
            // dejarlo y registrarlo después.
            $table->string('telefono')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Deshace la migración. dropIfExists() no reclama si la tabla ya
     * no está, así que un rollback repetido no truena.
     */
    public function down(): void
    {
        Schema::dropIfExists('comercios');
    }
};
