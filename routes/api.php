<?php

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\TransaccionApiController;
use App\Http\Resources\ComercioResource;
use App\Models\Comercio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de API — TaskBoard (Pasarela de Pagos)
|--------------------------------------------------------------------------
| La semana pasada este archivo cerró con una deuda escrita: sin
| sesión, estas rutas no sabían quién pedía las cosas, y cualquiera
| con la dirección del servidor podía leer, crear y borrar datos de
| todos los usuarios. El aislamiento de la Semana 12 no se cumplía
| acá. Esta semana se paga esa deuda, y el archivo cambia de forma.
|
| Lo que entra:
|
|   - Una ruta pública, /login, que entrega un token.
|   - Un grupo con el middleware auth:sanctum, donde vive TODO lo
|     demás. Sin token, nada de lo que está adentro se ejecuta: el
|     middleware corta antes y responde 401.
|   - Las consultas ya no arrancan en el modelo sino en el usuario
|     del token, así que cada quien ve lo suyo.
|
| Por qué el login queda afuera es casi un chiste, pero conviene
| decirlo: si exigiera token para pedir un token, nadie podría pedir
| el primero.
|
| El prefijo /api lo sigue poniendo bootstrap/app.php, así que acá se
| escribe '/login' y la dirección real es /api/login.
*/

/*
|--------------------------------------------------------------------------
| Zona pública — la única puerta sin llave
|--------------------------------------------------------------------------
| throttle:5,1 permite 5 intentos por minuto desde la misma IP y
| responde 429 al sexto. Es la misma defensa del login web de la
| Semana 12 y por el mismo motivo: sin límite, un script puede probar
| miles de contraseñas por minuto, y contra eso ninguna contraseña
| razonable alcanza.
|
| El límite se cuenta por IP y no por correo a propósito. Contarlo por
| correo dejaría que cualquiera bloquee la cuenta de otro fallando
| cinco veces con su dirección.
*/
Route::post('/login', [AuthApiController::class, 'login'])
    ->middleware('throttle:5,1');

/*
|--------------------------------------------------------------------------
| Zona protegida — todo lo demás
|--------------------------------------------------------------------------
| auth:sanctum lee el encabezado Authorization, le calcula el hash al
| token que venga, lo busca en personal_access_tokens y, si lo
| encuentra y no está vencido, deja pasar con el usuario de esa fila
| cargado en $request->user(). Si falta, está mal escrito, venció o
| fue revocado, responde 401 y el controlador nunca corre.
|
| Un detalle que se ve raro la primera vez: ese 401 llega en JSON solo
| si la petición trae Accept: application/json. Sin el encabezado,
| Laravel supone que del otro lado hay un navegador y responde con una
| redirección a la pantalla de login web, que para un programa no
| significa nada.
*/
Route::middleware('auth:sanctum')->group(function () {

    /*
    |----------------------------------------------------------------
    | Identidad y manejo de la sesión de API
    |----------------------------------------------------------------
    */

    // Quién es el dueño del token que acaba de entrar.
    Route::get('/yo', [AuthApiController::class, 'yo']);

    // Cerrar sesión de verdad: borra el token usado en esta petición.
    Route::post('/logout', [AuthApiController::class, 'logout']);

    // El reto del jueves: la lista de dispositivos con sesión abierta
    // y el cierre de uno solo de ellos.
    Route::get('/tokens', [AuthApiController::class, 'tokens']);
    Route::delete('/tokens/{id}', [AuthApiController::class, 'revocarToken']);

    /*
    |----------------------------------------------------------------
    | Comercios — solo lectura, y solo los propios
    |----------------------------------------------------------------
    | El cambio de la semana está en una línea. Antes:
    |
    |     Comercio::all()
    |
    | que significa "todos los comercios de la base". Ahora la
    | consulta arranca en el usuario del token:
    |
    |     $request->user()->comercios()
    |
    | que significa "los comercios de quien pregunta". La relación
    | hasMany del modelo User ya trae el where user_id armado, así que
    | el filtro no se escribe a mano y no se puede olvidar en una rama
    | del código.
    |
    | Siguen siendo funciones en la ruta y no un controlador porque
    | son dos endpoints de lectura y nada más.
    */

    // Listado. No carga la relación a propósito: el Resource solo
    // incluye la clave 'transacciones' cuando viene cargada, así que
    // acá no aparece y no se dispara una consulta por cada comercio
    // (el problema N+1 de la semana pasada).
    Route::get('/comercios', function (Request $request) {
        return ComercioResource::collection(
            $request->user()->comercios()->orderBy('nombre_comercio')->get()
        );
    });

    // Detalle. El route model binding resuelve el comercio por id SIN
    // preguntar de quién es, así que por sí solo deja entrar al
    // comercio de cualquiera: la verificación del dueño va en la
    // primera línea, antes de cargar la relación.
    //
    // abort_unless() con 404 y no con 403 es una decisión, no un
    // descuido. El 403 diría "existe, pero no es tuyo", y eso ya es
    // información: con ids correlativos se podría recorrer la tabla
    // contando cuáles contestan 403 para saber cuántos comercios hay
    // en el sistema y de paso cuáles son ajenos. El 404 no distingue
    // entre "no existe" y "no es tuyo", y para quien pregunta son
    // exactamente lo mismo.
    Route::get('/comercios/{comercio}', function (Request $request, Comercio $comercio) {
        abort_unless($comercio->user_id === $request->user()->id, 404);

        return new ComercioResource($comercio->load('transacciones'));
    });

    /*
    |----------------------------------------------------------------
    | Transacciones — lectura y escritura, filtradas por dueño
    |----------------------------------------------------------------
    | apiResource declara 5 rutas y no 7: deja fuera create y edit,
    | que en el CRUD web servían para MOSTRAR formularios. Una API no
    | tiene formularios.
    |
    | parameters() renombra el comodín de la URL. Sin esa línea
    | Laravel singulariza 'transacciones' en inglés y lo deja como
    | {transaccione}, que no coincide con el $transaccion del
    | controlador: el route model binding deja de resolver y los
    | métodos reciben un modelo vacío en vez de la fila pedida.
    |
    | El filtrado por dueño no se puede resolver acá, porque son cinco
    | métodos con cinco necesidades distintas; vive dentro del
    | controlador.
    */
    Route::apiResource('transacciones', TransaccionApiController::class)
        ->parameters(['transacciones' => 'transaccion']);
});

/*
|--------------------------------------------------------------------------
| La ruta /user que había dejado install:api
|--------------------------------------------------------------------------
| La semana pasada quedó en el archivo como punto de partida: devolvía
| el usuario dueño del token y respondía 401 siempre, porque todavía
| no se emitían tokens. Esta semana ya se emiten, y /api/yo hace lo
| mismo con la respuesta recortada a tres campos, así que la quito
| para no dejar dos direcciones que contestan lo mismo. Si se quisiera
| de vuelta, iría dentro del grupo auth:sanctum.
*/

/*
|--------------------------------------------------------------------------
| La ruta /prueba-error-bd del Laboratorio 4
|--------------------------------------------------------------------------
| Acá estuvo, mientras duró la práctica, una ruta que consultaba una
| tabla inexistente para provocar una QueryException a propósito y ver
| qué contestaba la API antes y después de configurar el manejador de
| excepciones. Era así:
|
|     Route::get('/prueba-error-bd', function () {
|         return DB::table('tabla_que_no_existe')->get();
|     });
|
| Se borró al terminar, como pide la guía, y por una razón que vale
| anotar: una ruta pública que hace fallar la base de datos a pedido es
| un regalo para quien quiera medir cómo responde el servidor bajo
| error, o simplemente llenar el log de basura llamándola en bucle.
| Queda el comentario para que se vea que existió y por qué ya no está.
*/
