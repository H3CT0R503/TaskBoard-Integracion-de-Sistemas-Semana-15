<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarTransaccionRequest;
use App\Models\Comercio;
use App\Models\Transaccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TransaccionController extends Controller
{
    /**
     * Revisión de propiedad de una transacción.
     *
     * Una transacción no guarda user_id: su dueño es el dueño de su
     * comercio, y la cadena para llegar ahí es
     * $t->comercio->user_id. Eso evita duplicar el dato en dos
     * tablas, que es la forma más común de que un día dejen de
     * coincidir.
     *
     * El método es privado y devuelve void porque no resuelve nada:
     * o deja seguir, o corta la petición con 404 desde adentro. Así
     * cada acción por id empieza con una sola línea en vez de
     * repetir el abort_unless cinco veces, y cuando haya que cambiar
     * el criterio se cambia acá nada más.
     */
    private function autorizar(Transaccion $t): void
    {
        abort_unless($t->comercio->user_id === Auth::id(), 404);
    }

    /**
     * Abre el formulario para registrar una transacción nueva.
     *
     * El parámetro llega como objeto Comercio y no como un id suelto,
     * así la vista muestra el nombre del comercio sin tener que
     * consultar nada más.
     *
     * El comercio viene en la URL, así que acá también se puede
     * escribir a mano el id de otra persona. La revisión va antes de
     * devolver la vista: sin ella, el formulario mostraría el nombre
     * de un comercio ajeno, que ya es filtrar información aunque el
     * insert después fallara.
     */
    public function create(Comercio $comercio): View
    {
        abort_unless($comercio->user_id === Auth::id(), 404);

        return view('transacciones.create', compact('comercio'));
    }

    /**
     * Inserta la transacción y regresa al panel del comercio.
     *
     * Al pedir GuardarTransaccionRequest en la firma, la validación
     * corre antes de la primera línea del método: si algo falla, la
     * ejecución nunca llega hasta acá. Y desde esta semana la regla
     * de comercio_id ya no acepta cualquier comercio existente, solo
     * los del usuario de la sesión, así que no necesito repetir la
     * revisión del dueño en el cuerpo.
     *
     * Tomo los campos con only() en lugar de all() para que un "estado"
     * agregado a mano desde el navegador se quede fuera del insert.
     *
     * Termino con un redirect y no con una vista para que al refrescar
     * con F5 el navegador no reenvíe el POST y duplique el registro.
     */
    public function store(GuardarTransaccionRequest $request): RedirectResponse
    {
        $transaccion = Transaccion::create($request->only([
            'comercio_id', 'cliente_nombre', 'monto',
        ]));

        return redirect()
            ->route('comercios.show', $transaccion->comercio_id)
            ->with('mensaje', 'Transacción registrada con éxito.');
    }

    /**
     * Carga el formulario de edición con los valores actuales.
     *
     * Acá no aparece ningún Transaccion::find(): el modelo ya viene
     * resuelto desde la ruta, y cuando el id no existe el 404 se lanza
     * solo, sin que yo escriba un abort().
     *
     * Mando también la lista de comercios porque el formulario permite
     * corregir a cuál pertenece la transacción. Esa lista ahora sale
     * de la relación del usuario: antes era Comercio::orderBy(...),
     * y con eso el desplegable ofrecía mover una transacción al
     * comercio de otra persona.
     */
    public function edit(Transaccion $transaccion): View
    {
        $this->autorizar($transaccion);

        $comercios = Auth::user()
            ->comercios()
            ->orderBy('nombre_comercio')
            ->get();

        return view('transacciones.edit', compact('transaccion', 'comercios'));
    }

    /**
     * Aplica los cambios del formulario de edición.
     *
     * Uso el mismo Form Request de store() a propósito: las reglas de
     * un monto o de un nombre de cliente no cambian porque el registro
     * ya exista, y mantener dos listas de reglas me obligaría a
     * corregir cualquier ajuste en dos lugares distintos.
     *
     * Las dos revisiones que corren acá responden preguntas
     * distintas, y por eso van las dos. autorizar() pregunta si la
     * transacción que se edita es mía; la regla de comercio_id del
     * Form Request pregunta si el comercio de destino también lo es.
     * Sin la primera, editaría una transacción ajena; sin la
     * segunda, movería la mía al comercio de otro.
     */
    public function update(
        GuardarTransaccionRequest $request,
        Transaccion $transaccion
    ): RedirectResponse {
        $this->autorizar($transaccion);

        $transaccion->update($request->validated());

        return redirect()
            ->route('comercios.show', $transaccion->comercio_id)
            ->with('mensaje', 'Transacción actualizada correctamente.');
    }

    /**
     * Borra la transacción, salvo que ya esté Completada.
     *
     * Una transacción Completada es dinero que de verdad se movió y la
     * única constancia que queda de ese cobro. Borrarla no corrige un
     * error: deja al comercio sin cómo demostrar que la operación
     * existió. Por eso acá el borrado se niega en lugar de ejecutarse,
     * y el usuario se va con el motivo en pantalla, no con un registro
     * desaparecido sin explicación.
     *
     * La revisión va en el controlador y no solo en la vista: esconder
     * el botón no detiene a quien arme la petición por su cuenta, y es
     * este método el que de verdad borra la fila.
     *
     * autorizar() va de primero, antes del aviso de "Completada". El
     * orden importa: si el mensaje saliera primero, una persona ajena
     * sabría que esa transacción existe y además en qué estado está.
     *
     * Guardo el id del comercio ANTES de delete(), porque después el
     * modelo queda sin fila detrás y el redirect no sabría a cuál
     * panel volver.
     */
    public function destroy(Transaccion $transaccion): RedirectResponse
    {
        $this->autorizar($transaccion);

        $comercioId = $transaccion->comercio_id;

        if ($transaccion->estado === 'Completada') {
            return redirect()
                ->route('comercios.show', $comercioId)
                ->with('error', 'No se puede eliminar una transacción Completada: es el respaldo de un cobro que ya se hizo.');
        }

        $transaccion->delete();

        return redirect()
            ->route('comercios.show', $comercioId)
            ->with('mensaje', 'Transacción eliminada.');
    }

    /**
     * Mueve únicamente el estado, sin abrir el formulario completo.
     *
     * Hago tres revisiones que resuelven problemas diferentes. La
     * primera es la de propiedad: mover el estado de una transacción
     * ajena cambia dinero que no es mío, y es la petición más fácil
     * de armar a mano porque el estado va en la URL. La segunda
     * descarta un estado que no existe en el sistema. La tercera
     * revisa el estado del que se parte: una transacción Completada
     * ya movió dinero real, y volverla a Fallida con un clic dejaría
     * la contabilidad diciendo algo que no ocurrió.
     *
     * El mapa de transiciones vive en el controlador porque es la
     * regla de negocio de este flujo, no una restricción de la tabla.
     */
    public function moverEstado(Transaccion $transaccion, string $estado): RedirectResponse
    {
        $this->autorizar($transaccion);

        $validos = ['Iniciada', 'Completada', 'Fallida'];

        if (! in_array($estado, $validos)) {
            abort(400, 'Estado no válido.');
        }

        $transiciones = [
            'Iniciada' => ['Completada', 'Fallida'],
            'Completada' => [],
            'Fallida' => ['Iniciada'],
        ];

        if (! in_array($estado, $transiciones[$transaccion->estado] ?? [])) {
            abort(400, "No se puede mover de {$transaccion->estado} a {$estado}.");
        }

        $transaccion->update(['estado' => $estado]);

        return redirect()
            ->route('comercios.show', $transaccion->comercio_id)
            ->with('mensaje', "Transacción movida a {$estado}.");
    }

    /*
     * Prueba que hice para confirmar que un Form Request sirve en
     * cualquier método y no solo en store(): validated() entrega nada
     * más los campos que pasaron por rules(). Lo dejo comentado para
     * no exponer una ruta que únicamente imprime datos en pantalla.
     *
     * public function duplicarUltima(GuardarTransaccionRequest $request)
     * {
     *     dd($request->validated());
     * }
     */
}
