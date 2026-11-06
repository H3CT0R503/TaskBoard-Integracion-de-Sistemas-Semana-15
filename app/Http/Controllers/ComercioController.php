<?php

namespace App\Http\Controllers;

use App\Models\Comercio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ComercioController extends Controller
{
    /**
     * GET /comercios — listado de los comercios del usuario.
     *
     * El cambio de esta semana está en la primera línea de la
     * consulta. Antes era Comercio::when(...), que arranca desde
     * TODA la tabla; ahora arranca desde Auth::user()->comercios(),
     * que es la relación hasMany y ya trae el where user_id puesto
     * por Eloquent.
     *
     * El dueño sale de la sesión y nunca del navegador. Si el
     * user_id llegara por GET o en un campo oculto, cambiarlo sería
     * tan fácil como editar la URL, y el filtro no estaría filtrando
     * nada.
     *
     * withCount('transacciones') agrega el total como una columna
     * calculada en la misma consulta. Sin eso, la vista pediría el
     * conteo comercio por comercio dentro del @forelse y terminaría
     * lanzando una consulta por fila (el problema N+1).
     *
     * Los dos filtros llegan por GET. when() aplica el where solo
     * cuando el campo trae algo, así que un buscador vacío o el rubro
     * en "Todos" devuelven la lista completa en lugar de nada.
     */
    public function index(Request $request): View
    {
        $comercios = Auth::user()
            ->comercios()
            ->when($request->buscar,
                fn ($q) => $q->where('nombre_comercio',
                    'like', "%{$request->buscar}%"))
            ->when($request->rubro,
                fn ($q) => $q->where('rubro', $request->rubro))
            ->withCount('transacciones')
            ->orderBy('nombre_comercio')
            ->get();

        // Los rubros del <select> salen de la propia tabla con
        // distinct(), para no mantener una lista fija que se
        // desactualice cada vez que se agrega un comercio nuevo.
        //
        // Esta consulta también sale de la relación. Si siguiera
        // leyendo la tabla completa, el desplegable delataría los
        // rubros de los demás: no mostraría sus comercios, pero sí
        // revelaría qué giros de negocio hay en el sistema.
        $rubros = Auth::user()
            ->comercios()
            ->select('rubro')
            ->distinct()
            ->orderBy('rubro')
            ->pluck('rubro');

        return view('comercios.index', compact('comercios', 'rubros'));
    }

    /**
     * GET /comercios/{comercio} — panel de un solo comercio.
     *
     * El {comercio} de la ruta entra convertido en modelo, y si el id
     * no existe la respuesta es 404 antes de ejecutar el método.
     *
     * Filtrar el listado no alcanza: que un comercio ajeno no
     * aparezca en pantalla no impide escribir /comercios/2 en la
     * barra de direcciones. Eso tiene nombre, IDOR, y el abort_unless
     * es lo que lo corta: el acceso por id se compara contra el
     * usuario de la sesión en cada visita.
     *
     * Devuelvo 404 y no 403 a propósito. Un 403 confirmaría que el
     * comercio 2 existe y que es de alguien más; el 404 responde lo
     * mismo que respondería un id inventado, así que la URL no sirve
     * ni para averiguar qué hay del otro lado.
     *
     * La comparación va con === y contra Auth::id(). Con == un '2'
     * de texto y un 2 numérico se darían por iguales, y confiar en
     * esa conversión en una revisión de seguridad es exactamente lo
     * que no se debe hacer.
     */
    public function show(Comercio $comercio): View
    {
        abort_unless($comercio->user_id === Auth::id(), 404);

        $comercio->load('transacciones');

        return view('comercios.show', compact('comercio'));
    }
}
