<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistroRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Registro, inicio y cierre de sesión.
 *
 * Los tres métodos que escriben algo terminan en redirect y no en
 * una vista, por la misma razón de siempre: si la respuesta de un
 * POST fuera una pantalla, un F5 reenviaría el formulario.
 */
class AuthController extends Controller
{
    /**
     * GET /registro — muestra el formulario de alta.
     */
    public function mostrarRegistro(): View
    {
        return view('auth.registro');
    }

    /**
     * POST /registro — crea el usuario y lo deja dentro.
     *
     * $request->validated() entrega únicamente los tres campos que
     * pasaron por rules(). Si alguien agrega un input "id" o un
     * "user_id" desde el navegador, no llega hasta el create().
     *
     * No hay Hash::make() en ninguna línea porque el cast 'hashed'
     * del modelo lo aplica al asignar la contraseña.
     *
     * regenerate() va justo después de Auth::login() y no antes:
     * cambia el identificador de sesión en el momento en que la
     * sesión pasa de anónima a identificada. Si se quedara el mismo
     * ID, alguien que hubiera conseguido ese valor antes del login
     * terminaría compartiendo la sesión ya autenticada; eso es la
     * fijación de sesión.
     */
    public function registrar(RegistroRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('comercios.index')
            ->with('mensaje', "¡Bienvenido, {$user->name}! Tu cuenta quedó creada.");
    }

    /**
     * GET /login — muestra el formulario de entrada.
     */
    public function mostrarLogin(): View
    {
        return view('auth.login');
    }

    /**
     * POST /login — verifica las credenciales y abre la sesión.
     *
     * Auth::attempt() hace tres cosas en una línea: busca el usuario
     * por correo, compara la contraseña escrita contra el hash
     * guardado con Hash::check() y, si coincide, inicia la sesión.
     * El segundo argumento es la casilla "recordarme", que cuando va
     * en true deja una cookie de larga duración con el
     * remember_token para no pedir la clave en cada visita.
     *
     * intended() es el detalle que hace que la experiencia no se
     * sienta rota: el middleware auth guardó la URL que la persona
     * pedía antes de mandarla al login, así que al entrar vuelve ahí
     * y no al listado. Si no había ninguna guardada, cae al
     * route() que paso como respaldo.
     *
     * El error es uno solo y genérico a propósito. Si dijera "ese
     * correo no existe", cualquiera podría probar direcciones hasta
     * armar la lista de quién tiene cuenta acá: eso es enumeración
     * de usuarios. Con onlyInput('email') el formulario vuelve con
     * el correo escrito, pero la contraseña nunca se repuebla.
     */
    public function login(Request $request): RedirectResponse
    {
        $datos = $request->only('email', 'password');

        if (Auth::attempt($datos, $request->boolean('recordar'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('comercios.index'));
        }

        return back()
            ->withErrors(['email' => 'Las credenciales no coinciden.'])
            ->onlyInput('email');
    }

    /**
     * POST /logout — cierra la sesión de verdad.
     *
     * Las tres líneas resuelven cosas distintas y por eso ninguna
     * sobra. Auth::logout() olvida al usuario; invalidate() destruye
     * los datos de la sesión en el servidor, que es donde vive lo
     * que la cookie solo apunta; regenerateToken() renueva el token
     * CSRF para que el formulario de la pantalla siguiente no quede
     * firmado con el token de una sesión ya muerta.
     *
     * Va por POST y no por un enlace GET: si /logout respondiera a
     * un GET, bastaría con que otra página web cargara una imagen
     * oculta apuntando ahí para sacar de la sesión a quien la
     * visitara. Un POST exige el @csrf del formulario, y ese token
     * una página ajena no lo tiene.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('mensaje', 'Cerraste sesión correctamente.');
    }
}
