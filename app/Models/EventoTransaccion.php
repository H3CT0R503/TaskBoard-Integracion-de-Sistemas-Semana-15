<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoTransaccion extends Model
{
    use HasFactory;

    /**
     * La tabla queda en singular ("eventos_transaccion") porque son
     * los eventos DE una transacción; el nombre no se deduce solo.
     */
    protected $table = 'eventos_transaccion';

    protected $fillable = [
        'transaccion_id',
        'tipo_evento',
        'detalle',
    ];

    /**
     * Un evento no existe por su cuenta: siempre pertenece a la
     * transacción que lo originó.
     */
    public function transaccion(): BelongsTo
    {
        return $this->belongsTo(Transaccion::class);
    }
}
