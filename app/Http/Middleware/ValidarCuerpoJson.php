<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Revisa el cuerpo de la petición antes de que nadie lo valide.
 *
 * Nace de dos casos de la bitácora del jueves, CL-13 y CL-14, que
 * tenían el mismo síntoma: la API respondía 422 diciendo que faltaban
 * comercio_id, cliente_nombre y monto, cuando los tres campos SÍ
 * venían en la petición.
 *
 * La causa es que Laravel solo convierte el cuerpo en datos cuando
 * puede: si el JSON está mal escrito, o si no viene el encabezado
 * Content-Type que dice que es JSON, el cuerpo queda sin leer y
 * $request->all() devuelve un arreglo vacío. La validación entonces
 * hace lo único que puede hacer con un arreglo vacío, que es reclamar
 * los campos obligatorios. El código 422 y el mensaje son coherentes
 * con lo que la validación vio; el problema es que lo que vio no era
 * la petición que se envió.
 *
 * Para quien está del otro lado, esa respuesta es peor que un error:
 * es una pista falsa. Le dice que revise tres campos que están bien
 * escritos, y no menciona la coma de más ni el encabezado que falta.
 *
 * Este middleware contesta antes, con los códigos que el protocolo ya
 * tiene para cada caso:
 *
 *   400 Bad Request            el cuerpo dice ser JSON y no se puede leer
 *   415 Unsupported Media Type el cuerpo viene sin decir en qué formato está
 *
 * Solo se aplica al grupo api, porque la parte web recibe formularios
 * (application/x-www-form-urlencoded) y ahí estas reglas no aplican.
 */
class ValidarCuerpoJson
{
    /**
     * Los métodos que traen cuerpo. GET y DELETE no mandan nada, así
     * que pasan de largo sin que se les revise nada.
     *
     * @var list<string>
     */
    private const CON_CUERPO = ['POST', 'PUT', 'PATCH'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), self::CON_CUERPO, true)) {
            return $next($request);
        }

        $cuerpo = $request->getContent();

        // Un POST sin cuerpo es raro pero no es un error de formato:
        // que lo resuelva la validación, que para eso está.
        if (trim($cuerpo) === '') {
            return $next($request);
        }

        // CL-14. isJson() mira el encabezado Content-Type, no el
        // contenido: un JSON perfecto enviado como texto plano cae
        // acá, y es correcto que caiga. El servidor no tiene por qué
        // adivinar el formato de lo que le mandan, y adivinar es justo
        // lo que abre la puerta a que interprete un cuerpo de una
        // manera distinta a la que el cliente quiso.
        if (! $request->isJson()) {
            return response()->json([
                'message' => 'El cuerpo debe enviarse con el encabezado Content-Type: application/json.',
            ], 415);
        }

        // CL-13. json_decode es la misma función que usa Laravel por
        // dentro; acá se llama antes, nada más, para poder distinguir
        // "el cuerpo no se pudo leer" de "al cuerpo le faltan campos".
        json_decode($cuerpo, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                // El mensaje de json_last_error_msg() se incluye
                // porque dice dónde está el problema ("Syntax error")
                // y no filtra nada del servidor: habla del texto que
                // mandó el propio cliente.
                'message' => 'El cuerpo no es un JSON válido: '.json_last_error_msg().'.',
            ], 400);
        }

        return $next($request);
    }
}
