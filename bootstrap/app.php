<?php

use App\Http\Middleware\ValidarCuerpoJson;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
        |------------------------------------------------------------
        | Revisión del cuerpo antes de validar
        |------------------------------------------------------------
        | Va solo en el grupo api. Es la corrección de los casos CL-13
        | y CL-14 de la bitácora: un JSON mal escrito ahora responde
        | 400 y un cuerpo sin Content-Type responde 415, en vez de los
        | dos caer en el mismo 422 diciendo que faltan campos que en
        | realidad sí venían.
        |
        | El orden importa: tiene que correr ANTES de que el Form
        | Request valide, y por eso es un middleware de grupo y no una
        | regla de validación. Cuando la validación entra en escena, el
        | cuerpo ya se intentó leer y el daño ya está hecho.
        */
        $middleware->appendToGroup('api', [
            ValidarCuerpoJson::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
        |------------------------------------------------------------
        | En /api, la respuesta es JSON aunque falte el Accept
        |------------------------------------------------------------
        | Hasta la semana pasada, una petición a la API sin el
        | encabezado Accept: application/json recibía la respuesta
        | pensada para un navegador: una redirección 302 a la pantalla
        | de login, o una página HTML de error. Era coherente con cómo
        | funciona Laravel, pero no con lo que es esta parte del
        | proyecto: bajo /api nunca hay un navegador esperando una
        | pantalla, hay un programa esperando datos.
        |
        | shouldRenderJsonWhen cambia esa decisión para /api y la deja
        | donde corresponde, en el servidor. Un cliente que se olvide
        | del encabezado ahora recibe el error de verdad en lugar de
        | una pista falsa.
        |
        | Eso NO quiere decir que el Accept deje de importar. Sigue
        | siendo la forma en que el cliente dice qué entiende, y es lo
        | que permitiría que mañana la misma dirección devuelva otro
        | formato. Lo que cambia es que ya no es la diferencia entre
        | recibir un error útil y recibir una página de HTML.
        */
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );

        /*
        |------------------------------------------------------------
        | Falla de base de datos: lo justo para el cliente, todo para el log
        |------------------------------------------------------------
        | Una QueryException es culpa del servidor, no de quien pide, y
        | eso decide las dos mitades de la respuesta.
        |
        | Lo que el cliente recibe es un 500 con una sola frase. No
        | porque sea más bonito, sino porque el detalle de la falla
        | incluye la consulta SQL completa, el nombre de las tablas, la
        | conexión, el host y las rutas de los archivos del servidor.
        | Con APP_DEBUG=true eso salía entero en la respuesta: un mapa
        | de la base de datos entregado a quien preguntara.
        |
        | Lo que el servidor guarda es todo lo contrario: el log recibe
        | la excepción completa, con su consulta y su pila, porque ahí
        | sí hace falta para arreglar el problema. El detalle no
        | desaparece, cambia de destinatario.
        |
        | No hace falta llamar a report(): Laravel escribe el log solo.
        | Este callback solo decide lo que se responde.
        */
        $exceptions->render(function (QueryException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'No se pudo guardar la información. Intenta de nuevo más tarde.',
                ], 500);
            }
        });

        /*
        |------------------------------------------------------------
        | El 404 de la API, contestado como lo contestaría una API
        |------------------------------------------------------------
        | Viene de la Semana 14 y se queda igual. Con APP_DEBUG=true,
        | un abort(404) o un id inexistente respondían el código
        | correcto pero con cientos de líneas: el nombre de la
        | excepción, la ruta absoluta de los archivos en mi disco y
        | toda la pila de llamadas.
        |
        | El mensaje es uno solo para los dos casos de 404, el id que
        | no existe y el recurso ajeno, igual que en el controlador: un
        | 403 o un texto distinto confirmaría que esa fila existe en la
        | cuenta de alguien más.
        */
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'No encontramos ese recurso en tu cuenta.',
                ], 404);
            }
        });
    })->create();
