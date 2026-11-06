<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * La puerta de la API: emitir, leer y revocar tokens.
 *
 * Es el equivalente del AuthController web de la Semana 12, pero sin
 * una sola de sus piezas. Allá el login abría una sesión, el servidor
 * guardaba un archivo con el id del usuario y el navegador recibía
 * una cookie que reenviaba sola en cada clic. Acá no hay nada de eso:
 * el login entrega un token, el cliente lo guarda donde pueda y lo
 * manda a mano en el encabezado Authorization de cada petición.
 *
 * La diferencia no es de estilo. Una aplicación móvil, un script o
 * otro servidor no tienen cookies ni navegador que las administre, y
 * el servidor de la API no recuerda nada entre una petición y la
 * siguiente. El token existe porque cada petición tiene que llegar
 * identificada por su cuenta, sin apoyarse en lo que pasó antes.
 *
 * Códigos que usa este controlador:
 *   201  se creó un token (login)
 *   200  acá está lo que pediste (yo, tokens)
 *   204  se hizo y no hay nada que devolver (logout, revocar)
 *   401  no sé quién eres (lo lanza el middleware auth:sanctum)
 *   422  los datos no pasaron la validación
 */
class AuthApiController extends Controller
{
    /**
     * POST /api/login — verifica credenciales y entrega un token.
     *
     * Es la única ruta de la API que queda fuera de auth:sanctum, por
     * una razón circular: si exigiera token, nadie podría conseguir
     * el primero.
     *
     * El campo 'dispositivo' no es adorno. Cada token nace con un
     * nombre, y ese nombre es lo que después permite distinguir
     * "laptop-ana" de "app-movil-ana" en la lista de sesiones
     * abiertas y revocar solo una de las dos.
     */
    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'dispositivo' => ['required', 'string', 'max:100'],
        ]);

        $user = User::where('email', $datos['email'])->first();

        // Las dos condiciones van en el mismo if y con el mismo
        // mensaje a propósito. Si el correo inexistente respondiera
        // "ese usuario no existe" y la clave mala respondiera "clave
        // incorrecta", la API serviría para averiguar qué correos
        // tienen cuenta: se prueban mil direcciones y las que
        // contesten distinto son clientes del sistema. Eso se llama
        // enumeración de cuentas y acá se cierra con un mensaje que
        // no distingue los dos casos.
        //
        // Hash::check() compara la clave escrita contra el hash
        // guardado. Nunca se descifra el hash, porque no se puede:
        // se vuelve a cifrar lo que llegó y se comparan los dos
        // resultados.
        if (! $user || ! Hash::check($datos['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no coinciden.',
            ]);
        }

        // createToken() devuelve un objeto NewAccessToken con dos
        // partes: el modelo guardado (que lleva el hash) y
        // plainTextToken, el texto completo "7|aB9x...".
        //
        // Ese texto existe solo en esta respuesta. En la tabla queda
        // únicamente su hash, así que ni yo con acceso a la base de
        // datos puedo recuperarlo después. Es la misma idea que las
        // contraseñas de la Semana 12, aplicada a las llaves.
        $token = $user->createToken($datos['dispositivo']);

        return response()->json([
            'token' => $token->plainTextToken,
            'tipo' => 'Bearer',
            // Devuelvo también a quién pertenece para que el cliente
            // pueda saludar por el nombre sin una segunda petición.
            'usuario' => $user->only('id', 'name', 'email'),
        ], 201);
    }

    /**
     * GET /api/yo — quién es el dueño del token que acaba de entrar.
     *
     * $request->user() dentro de auth:sanctum no sale de una sesión
     * ni de un parámetro de la URL: sale del token. Sanctum toma el
     * encabezado Authorization, le calcula el hash, lo busca en
     * personal_access_tokens y carga el usuario de esa fila.
     *
     * only() recorta la respuesta a tres campos. Devolver el modelo
     * completo publicaría email_verified_at y las fechas internas, y
     * la contraseña solo se queda afuera porque $hidden la frena: no
     * quiero que la seguridad de este endpoint dependa de un
     * descuido en otro archivo.
     */
    public function yo(Request $request): array
    {
        return $request->user()->only('id', 'name', 'email');
    }

    /**
     * POST /api/logout — revoca el token con el que se pidió.
     *
     * En la web cerrar sesión era invalidar la sesión y regenerar el
     * token CSRF. Acá no hay sesión que destruir: cerrar sesión es
     * borrar la fila del token, y desde ese instante el mismo texto
     * que servía hace un segundo responde 401.
     *
     * currentAccessToken() es la pieza fina. Borra SOLO el token con
     * el que viene firmada esta petición, así que cerrar sesión en el
     * teléfono no bota la sesión de la laptop. Para salir de todos
     * los dispositivos de golpe sería $request->user()->tokens()->delete(),
     * que es lo que hacen los botones de "cerrar sesión en todas
     * partes".
     */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * GET /api/tokens — el reto del jueves: ¿dónde tengo sesión abierta?
     *
     * Es la pantalla de "dispositivos conectados" que tienen casi
     * todas las aplicaciones. Lista los tokens del usuario dueño del
     * token que entró, y nada más: tokens() ya trae el where
     * tokenable_id armado por la relación, así que no hay forma de
     * pedir los de otra persona ni equivocándose.
     *
     * El get() lleva la lista de columnas escrita a mano, y esa lista
     * es la parte importante del reto: la columna 'token' guarda el
     * hash y NO sale. Publicarlo no entregaría la llave (el hash no
     * se puede invertir), pero sí entregaría el material para
     * intentar adivinarla sin límite y fuera de la vista del
     * servidor. Una lista de sesiones no necesita el secreto para
     * cumplir su función.
     *
     * last_used_at es el dato que le da sentido a la pantalla: un
     * token que nadie usó en tres meses es un candidato a revocar.
     */
    public function tokens(Request $request): JsonResponse
    {
        // orderByDesc('id') y no latest(): dos tokens emitidos en el
        // mismo segundo tienen el MISMO created_at, y ordenar por esa
        // columna deja el empate en manos de MySQL, que lo resuelve
        // distinto cada vez. El id siempre crece, así que el más
        // nuevo queda arriba sin ambigüedad.
        $tokens = $request->user()
            ->tokens()
            ->orderByDesc('id')
            ->get(['id', 'name', 'last_used_at', 'created_at']);

        return response()->json([
            'data' => $tokens->map(fn ($token) => [
                'id' => $token->id,
                'dispositivo' => $token->name,
                // El mismo criterio del Resource de la Semana 13:
                // las fechas salen en ISO 8601 y el formato visual lo
                // decide quien muestra el dato. El null se conserva
                // como null, porque "nunca se ha usado" es una
                // respuesta distinta a una fecha cualquiera.
                'ultimo_uso' => $token->last_used_at?->toIso8601String(),
                'creado' => $token->created_at->toIso8601String(),
                // Marca cuál de todos es el token con el que se está
                // viendo esta lista, igual que el "este dispositivo"
                // de las apps reales.
                'es_el_actual' => $token->id === $request->user()->currentAccessToken()->id,
            ]),
        ]);
    }

    /**
     * DELETE /api/tokens/{id} — el extra del reto: cerrar la sesión
     * de un dispositivo concreto.
     *
     * La pregunta que deja planteada la guía es qué hay que verificar
     * antes de borrar, y la respuesta es que el token sea del usuario
     * que pide el borrado. Sin esa verificación, cualquiera con una
     * cuenta podría ir probando números de id y cerrarle la sesión a
     * los demás.
     *
     * Acá la verificación no es un if aparte: la consulta arranca en
     * $request->user()->tokens(), así que un id ajeno simplemente no
     * aparece en el conjunto y findOrFail levanta un 404. Es el mismo
     * criterio del resto de la API: lo que no es tuyo no existe para
     * ti, y un 404 no confirma que ese id exista en otra cuenta.
     */
    public function revocarToken(Request $request, string $id): Response
    {
        $token = $request->user()->tokens()->findOrFail($id);

        $token->delete();

        return response()->noContent();
    }
}
