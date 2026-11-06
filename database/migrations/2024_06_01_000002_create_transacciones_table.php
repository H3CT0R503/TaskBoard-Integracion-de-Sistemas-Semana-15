<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Transacciones de cada comercio.
     *
     * foreignId()->constrained() crea la columna comercio_id y además
     * la restricción en la base de datos, no solo en el código: un
     * insert con un comercio inexistente lo rechaza MySQL.
     *
     * cascadeOnDelete() decide qué pasa al borrar el comercio: sus
     * transacciones se van con él, porque no significan nada sueltas.
     *
     * decimal(8,2) y no float, porque el dinero no admite los errores
     * de redondeo del punto flotante.
     */
    public function up(): void
    {
        Schema::create('transacciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comercio_id')
                ->constrained('comercios')
                ->cascadeOnDelete();
            $table->decimal('monto', 8, 2);
            $table->string('cliente_nombre');
            // El enum deja la lista de estados válidos dentro de la
            // base de datos, y toda transacción nace como Iniciada.
            $table->enum('estado', ['Iniciada', 'Completada', 'Fallida'])
                ->default('Iniciada');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transacciones');
    }
};
