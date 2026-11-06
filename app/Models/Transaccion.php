<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaccion extends Model
{
    use HasFactory;

    /**
     * Sin esta línea Eloquent buscaría la tabla "transaccions",
     * porque pluraliza en inglés y no reconoce el nombre en español.
     */
    protected $table = 'transacciones';

    /**
     * Campos que create() y update() tienen permitido escribir. El
     * estado está incluido porque moverEstado() lo actualiza, pero el
     * formulario de registro no lo manda: store() toma solo tres
     * campos con only(), así que nadie lo fuerza desde el navegador.
     */
    protected $fillable = [
        'comercio_id',
        'monto',
        'cliente_nombre',
        'estado',
    ];

    /**
     * El monto vuelve de la base de datos como cadena. El cast lo
     * entrega siempre con dos decimales, así los cálculos y la vista
     * no dependen de cómo lo devolvió el driver.
     */
    protected $casts = [
        'monto' => 'decimal:2',
    ];

    /**
     * El lado inverso de la relación: toda transacción tiene detrás un
     * comercio, y la llave comercio_id es la que lo apunta.
     */
    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class);
    }

    /**
     * Bitácora de la transacción. Cada movimiento deja su evento, y
     * por eso una transacción con eventos no se puede borrar sin
     * decidir primero qué pasa con ellos.
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(EventoTransaccion::class);
    }
}
