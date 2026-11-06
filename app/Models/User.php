<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /**
     * HasApiTokens es lo que entra esta semana, y es el trait que
     * convierte a este modelo en alguien capaz de emitir tokens.
     * Agrega tres cosas que el controlador de la API usa:
     *
     *   createToken('nombre')  emite un token nuevo y guarda SU HASH
     *                          en la tabla personal_access_tokens
     *   tokens()               la relación con todos sus tokens
     *   currentAccessToken()   el token exacto con el que viene
     *                          firmada la petición actual
     *
     * Sin esta línea createToken() no existe, y el login de la API
     * truena con "Call to undefined method". Ese es justo el error C
     * del laboratorio de depuración del viernes, y el que más cuesta
     * ver, porque el archivo que falla (el controlador) no es el
     * archivo que está mal (este).
     *
     * @use HasFactory<UserFactory>
     */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Campos que create() tiene permitido escribir. La contraseña
     * entra acá porque el registro la manda, pero nunca llega en
     * texto plano a la tabla: el cast de abajo la cifra antes.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * Lo que queda fuera cuando el modelo se convierte a arreglo o a
     * JSON. Si mañana una ruta devuelve un User, el hash y el token
     * de "recordarme" no se escapan en la respuesta.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * El cast 'hashed' es la pieza clave del día: al asignar
     * password, Eloquent le aplica Hash::make() solo. Por eso el
     * controlador de registro no llama a Hash en ninguna línea, y
     * aun así la tabla jamás guarda la clave legible.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Un usuario es dueño de sus comercios.
     *
     * Este es el lado hasMany de la relación, y con él declarado
     * $user->comercios() ya trae el where user_id armado por
     * Eloquent. Eso es lo que permite que el listado filtre por el
     * usuario de la sesión sin que yo escriba la consulta, y sin
     * recibir el dueño desde el formulario.
     */
    public function comercios(): HasMany
    {
        return $this->hasMany(Comercio::class);
    }

    /**
     * Las transacciones del usuario, pasando por sus comercios.
     *
     * En la tabla transacciones no hay columna user_id, y no la
     * quiero: el dueño de una transacción es el dueño de su
     * comercio, y guardar el dato dos veces abre la puerta a que las
     * dos columnas se contradigan. hasManyThrough recorre la cadena
     * user -> comercios -> transacciones con un solo JOIN.
     *
     * Entra esta semana porque el filtrado de la API pregunta
     * exactamente esto, y tenerlo acá evita repetir el whereHas en
     * cada método del controlador.
     */
    public function transacciones(): HasManyThrough
    {
        return $this->hasManyThrough(Transaccion::class, Comercio::class);
    }
}
