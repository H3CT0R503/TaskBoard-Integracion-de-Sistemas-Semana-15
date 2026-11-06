<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarTransaccionRequest;
use App\Http\Resources\TransaccionResource;
use App\Models\Transaccion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * La misma información que el controlador web, por la otra puerta.
 *
 * Ningún método devuelve una vista: todos devuelven un Resource, y
 * Laravel lo convierte en JSON. Tampoco hay redirects, porque del
 * otro lado no hay navegador que siga una redirección; lo que hay es
 * un programa que espera un código de estado y un cuerpo.
 *
 * Los códigos que usa este controlador:
 *   200  la consulta salió bien
 *   201  se creó un recurso (store)
 *   204  se hizo lo pedido y no hay nada que devolver (destroy)
 *   401  no sé quién eres (lo lanza auth:sanctum, antes de entrar acá)
 *   404  ese id no existe, o no existe PARA TI
 *   422  los datos no pasaron la validación (lo lanza el Form Request)
 *
 * Lo que cambia esta semana es el 404. La semana pasada significaba
 * una sola cosa, "ese id no está en la tabla", y lo lanzaba solo el
 * route model binding. Ahora significa dos, porque el controlador
 * también lo lanza cuando la fila existe pero es de otra persona.
 * Juntar los dos casos en el mismo código es deliberado: un 403
 * confirmaría que la transacción 4 existe y es de alguien más, y eso
 * ya es un dato que no se le debe a quien pregunta.
 *
 * El dueño de una transacción no está en la tabla transacciones: es
 * el dueño de su comercio. Por eso toda la autorización de este
 * archivo pasa por la relación comercio.
 */
class TransaccionApiController extends Controller
{
    /**
     * La verificación de propiedad, en un solo lugar.
     *
     * La escribo como método privado y no copiada en cada acción por
     * la razón de siempre: tres copias de la misma regla son tres
     * oportunidades de corregir solo dos. Si mañana la regla cambia
     * (por ejemplo, que un comercio pueda tener varios usuarios
     * autorizados), cambia acá y las tres acciones quedan al día.
     *
     * abort_unless() corta la ejecución y lanza la excepción HTTP:
     * si la condición es falsa, el resto del método nunca corre. Eso
     * es importante en update() y destroy(), donde seguir adelante
     * significaría escribir o borrar la fila de otra persona.
     *
     * auth()->id() dentro de auth:sanctum devuelve el dueño del
     * token, no el de una sesión: es el mismo valor que
     * $request->user()->id, nada más que sin tener que recibir el
     * Request en un método que no lo necesita para otra cosa.
     */
    private function autorizar(Transaccion $transaccion): void
    {
        abort_unless($transaccion->comercio->user_id === auth()->id(), 404);
    }

    /**
     * GET /api/transacciones — listado, con filtro opcional por estado.
     *
     * El reto de la guía. El parámetro llega por la URL
     * (?estado=Completada) y when() aplica el where solo si trae
     * algo, así que sin el parámetro la consulta queda exactamente
     * como estaba y el listado sigue devolviendo todo.
     *
     * Un estado que no existe, o uno válido sin filas, devuelve una
     * lista vacía con 200 y no un 404. El 404 significa "esa
     * dirección no corresponde a ningún recurso", y /api/transacciones
     * sí existe: la consulta se entendió y se resolvió bien. Que el
     * resultado tenga cero elementos es una respuesta correcta, no un
     * error. Devolver 404 obligaría al cliente a tratar como fallo
     * algo que simplemente es una lista sin elementos.
     *
     * latest() ordena por created_at descendente: lo más reciente
     * primero, que es lo que espera quien consulta movimientos.
     *
     * El cambio de esta semana es de dónde arranca la consulta. Antes
     * era Transaccion::query(), o sea la tabla completa, y con eso
     * Ana veía los cobros de Carlos. Ahora arranca en
     * $request->user()->transacciones(), la relación hasManyThrough
     * que recorre user -> comercios -> transacciones, así que el
     * filtro por dueño va en el JOIN y no en un where que se pueda
     * olvidar.
     *
     * El latest() lleva la columna escrita con su tabla
     * ('transacciones.created_at') justamente por ese JOIN: comercios
     * también tiene created_at, y un orderBy a secas deja MySQL con
     * dos columnas del mismo nombre y un error de columna ambigua.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $estado = $request->query('estado');

        $transacciones = $request->user()->transacciones()
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->latest('transacciones.created_at')
            ->get();

        return TransaccionResource::collection($transacciones);
    }

    /**
     * POST /api/transacciones — crea una transacción.
     *
     * Reutiliza el Form Request del CRUD web a propósito: las reglas
     * de un monto o de un nombre de cliente no cambian porque la
     * petición venga de un programa en vez de un formulario. Si algo
     * falla, la ejecución nunca llega a la primera línea del método y
     * Laravel responde 422 con los errores en JSON, siempre que la
     * petición lleve Accept: application/json.
     *
     * El 201 se pone a mano porque el valor por defecto de un Resource
     * es 200, y 200 significa "acá está lo que pediste" mientras que
     * 201 significa "se creó algo nuevo". Para un cliente la
     * diferencia importa: con 201 sabe que hay un recurso que antes
     * no existía, sin tener que deducirlo del cuerpo.
     *
     * El campo estado no se acepta aunque venga en la petición. Hasta
     * la semana pasada eso lo resolvía el only() de abajo, que lo
     * dejaba fuera sin decir nada; desde esta semana lo resuelve la
     * regla 'estado' => ['prohibited'] del Form Request, que responde
     * 422 y explica la regla. El only() se queda igual: es la segunda
     * cerradura de la misma puerta, y si mañana alguien afloja la
     * regla, la columna sigue sin poder llegar desde la petición.
     *
     * Las dos escrituras van dentro de DB::transaction(), y esa es la
     * parte nueva del día. Crear la transacción y registrar su evento
     * de creación son dos INSERT distintos en dos tablas distintas, y
     * entre uno y otro puede fallar cualquier cosa: la base se cae, el
     * disco se llena, el proceso se muere. Sin la transacción, lo que
     * queda guardado es el primer INSERT y nada más: un cobro sin su
     * evento de creación, o sea una fila que la bitácora de la
     * aplicación no puede explicar, con un cliente que recibió un 500
     * y cree que no se guardó nada.
     *
     * DB::transaction() hace que las dos escrituras sean una sola
     * cosa: si el closure termina bien, se confirman las dos (COMMIT);
     * si lanza una excepción, se deshacen las dos (ROLLBACK). No hay
     * estado intermedio que pueda quedar guardado. Es todo o nada, y
     * en una pasarela de pagos "a medias" es peor que "nada", porque
     * nada se puede reintentar y a medias hay que ir a averiguar qué
     * pasó.
     *
     * El throw de prueba del experimento de integridad iba
     * exactamente entre los dos create() de abajo. Con el código del
     * Paso A quedaba la transacción sin evento; con el Paso B, que es
     * este, no quedaba nada.
     *
     * Este método es el único de los cinco que NO necesitó una línea
     * nueva esta semana, y vale la pena entender por qué. La regla de
     * que el comercio tiene que ser del usuario ya estaba escrita
     * desde la Semana 12, dentro de GuardarTransaccionRequest, en el
     * exists con where user_id. Lo que faltaba no era la regla sino
     * el usuario: por la API $this->user() era null y la regla caía a
     * un exists simple. Ahora el token trae identidad en cada
     * petición, así que la misma regla de siempre volvió a tener
     * contra quién comparar, y si Ana intenta crear una transacción
     * en un comercio de Carlos recibe 422 sin que yo haya tocado una
     * sola línea del Form Request.
     */
    public function store(GuardarTransaccionRequest $request): JsonResponse
    {
        $transaccion = DB::transaction(function () use ($request) {
            $transaccion = Transaccion::create($request->only([
                'comercio_id', 'cliente_nombre', 'monto',
            ]));

            // refresh() relee la fila recién insertada.
            //
            // Hace falta porque 'estado' no viaja en la petición: lo
            // pone la base de datos con el default de la migración.
            // create() devuelve el modelo con los campos que YO le
            // pasé, y como ese no era uno de ellos, el objeto en
            // memoria lo tiene en null aunque la fila ya diga
            // 'Iniciada'. Sin esta línea la respuesta 201 sale con
            // "estado": null y el cliente cree que la transacción
            // nació sin estado.
            $transaccion->refresh();

            // El segundo INSERT, en otra tabla. create() desde la
            // relación llena transaccion_id solo.
            $transaccion->eventos()->create([
                'tipo_evento' => 'creacion',
                'detalle' => 'Transacción iniciada desde la API',
            ]);

            return $transaccion;
        });

        return (new TransaccionResource($transaccion))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/transacciones/{transaccion} — una sola transacción.
     *
     * El modelo entra resuelto desde la ruta, y si el id no existe el
     * 404 se lanza antes de ejecutar el método. Con el encabezado
     * Accept: application/json ese 404 llega como JSON y no como la
     * página HTML de error.
     *
     * El route model binding resuelve por id y nada más: encuentra la
     * fila sin preguntar de quién es. Por eso la autorización va en
     * la primera línea, antes de construir el Resource.
     */
    public function show(Transaccion $transaccion): TransaccionResource
    {
        $this->autorizar($transaccion);

        return new TransaccionResource($transaccion);
    }

    /**
     * PUT/PATCH /api/transacciones/{transaccion} — actualiza.
     *
     * apiResource declara esta ruta, así que el método se implementa
     * para no dejar un 501 colgando. Usa el mismo Form Request, por
     * la misma razón que store().
     *
     * Acá hacen falta las DOS verificaciones, y son distintas:
     *
     *   autorizar()    la transacción que se va a modificar es mía
     *   Form Request   el comercio_id que llega en el cuerpo es mío
     *
     * Sin la primera, Ana podría editar una transacción de Carlos.
     * Sin la segunda, Ana podría tomar una transacción suya y
     * mudársela al comercio de Carlos, que es la misma fuga en el
     * otro sentido.
     *
     * El orden en que se ven importa al momento de probar: el Form
     * Request corre ANTES del cuerpo del método, así que un PUT sobre
     * una transacción ajena con un comercio_id también ajeno
     * responde 422 y no 404. Para ver el 404 hay que mandar un
     * comercio_id propio y un id de transacción ajeno, que es lo que
     * hace la petición de la colección.
     */
    public function update(
        GuardarTransaccionRequest $request,
        Transaccion $transaccion
    ): TransaccionResource {
        $this->autorizar($transaccion);

        $transaccion->update($request->validated());

        return new TransaccionResource($transaccion);
    }

    /**
     * DELETE /api/transacciones/{transaccion} — elimina.
     *
     * Devuelve 204: se hizo lo que se pidió y no hay cuerpo que
     * mandar. Devolver la transacción borrada sería raro, porque ya
     * no existe; devolver un mensaje de éxito obligaría al cliente a
     * leer el cuerpo para enterarse de algo que el código ya dice.
     *
     * Un segundo DELETE al mismo id responde 404, porque el route
     * model binding ya no encuentra la fila. Eso es correcto, y es
     * justo lo que la tabla de verificación pide comprobar.
     *
     * Nota de la semana pasada: este destroy no repetía la regla del
     * CRUD web, que se niega a borrar una transacción Completada, y
     * la deuda quedó anotada acá porque la API no tenía a quién
     * rendirle cuentas. Esta semana la primera mitad de esa deuda se
     * paga: ya no se puede borrar lo ajeno, porque autorizar() corta
     * con 404 antes del delete.
     *
     * La segunda mitad sigue abierta a propósito, y es la regla de
     * negocio: por la API, el dueño de una transacción Completada
     * todavía la puede borrar. No es un olvido ni un problema de
     * identidad, es una decisión de diseño que no me toca tomar sola:
     * una pasarela de pagos real no borra un cobro cerrado, lo
     * anula. Queda escrito acá para que se vea que la diferencia
     * entre las dos puertas es conocida y no accidental.
     */
    public function destroy(Transaccion $transaccion): Response
    {
        $this->autorizar($transaccion);

        $transaccion->delete();

        return response()->noContent();
    }
}
