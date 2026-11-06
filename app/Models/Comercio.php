<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comercio extends Model
{
    use HasFactory;

    /**
     * Única lista de campos que create() y update() tienen permitido
     * escribir. Lo que no esté acá se ignora aunque venga en la
     * petición, y eso es justo lo que frena el mass assignment.
     *
     * user_id entra en la lista porque el seeder crea los comercios
     * desde la relación ($ana->comercios()->create([...])) y ahí
     * Eloquent lo asigna solo. Que esté en $fillable no significa
     * que un formulario pueda mandarlo: ningún formulario del
     * sistema tiene ese campo, y el dueño siempre sale de la sesión.
     */
    protected $fillable = [
        'user_id',
        'nombre_comercio',
        'rubro',
        'telefono',
    ];

    /**
     * Un comercio agrupa todas sus transacciones.
     *
     * Con esta relación declarada, $comercio->transacciones devuelve
     * la colección completa y Eloquent arma el where por comercio_id
     * sin que yo escriba la consulta.
     */
    public function transacciones(): HasMany
    {
        return $this->hasMany(Transaccion::class);
    }

    /**
     * El dueño del comercio: el lado belongsTo de la relación que
     * User declara con hasMany.
     *
     * Le paso 'user_id' de forma explícita porque el método se llama
     * usuario() y no user(): sin ese segundo argumento, Eloquent
     * buscaría una columna usuario_id que no existe.
     *
     * Esta relación es la que permite llegar al dueño desde el otro
     * extremo de la cadena, y de ahí sale la autorización de las
     * transacciones: $transaccion->comercio->user_id.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
