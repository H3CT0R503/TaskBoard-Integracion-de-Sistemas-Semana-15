<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bitácora de lo que le pasa a cada transacción, para poder
     * reconstruir después qué ocurrió y cuándo.
     *
     * La clave foránea empezó con un ->constrained() a secas, y con
     * eso MySQL bloqueaba el DELETE de cualquier transacción que ya
     * tuviera eventos: el error 1451 de integridad referencial. Era la
     * base de datos protegiendo los datos, no una falla del código.
     *
     * onDelete('cascade') resuelve esa decisión: los eventos son parte
     * de la transacción y se borran con ella. Lo contrario sería
     * bloquear el DELETE, y tiene sentido cuando el hijo vale por sí
     * mismo (una factura emitida, por ejemplo), pero un evento sin su
     * transacción solo queda huérfano.
     */
    public function up(): void
    {
        Schema::create('eventos_transaccion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaccion_id')
                ->constrained('transacciones')
                ->onDelete('cascade');
            $table->string('tipo_evento');
            $table->text('detalle')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_transaccion');
    }
};
