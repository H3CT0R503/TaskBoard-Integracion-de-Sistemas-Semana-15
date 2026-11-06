<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega el dueño a cada comercio.
     *
     * Hasta la semana pasada un comercio no pertenecía a nadie: la
     * tabla no tenía con qué responder "¿de quién es esta fila?", y
     * por eso cualquier sesión veía todo. Esta columna es la que
     * vuelve contestable esa pregunta.
     *
     * Schema::table() y no Schema::create(): la tabla ya existe y
     * acá solo se le suma una columna.
     *
     * Tres decisiones en las cuatro líneas de la columna:
     *
     * - foreignId('user_id') crea un entero sin signo con el nombre
     *   exacto que Eloquent espera para hasMany/belongsTo.
     * - constrained() deduce que apunta a users.id y crea la clave
     *   foránea. Con eso la base rechaza un user_id inventado; la
     *   regla no depende de que el código se acuerde de validar.
     * - onDelete('cascade') borra los comercios del usuario que se
     *   elimine. Dejarlos sin dueño sería peor: filas que ninguna
     *   consulta filtrada volvería a mostrar, invisibles pero ahí.
     *
     * La columna va NOT NULL a propósito: un comercio sin dueño es
     * justo el agujero que esta semana se cierra. Eso obliga a
     * correr migrate:fresh --seed si ya hay comercios de semanas
     * anteriores, porque MySQL no sabría qué user_id ponerles.
     */
    public function up(): void
    {
        Schema::table('comercios', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->after('id')
                ->constrained()
                ->onDelete('cascade');
        });
    }

    /**
     * Deshace la migración.
     *
     * dropForeign() va antes de dropColumn() y el orden no es
     * negociable: mientras la restricción exista, MySQL no deja
     * quitar la columna de la que depende.
     */
    public function down(): void
    {
        Schema::table('comercios', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
