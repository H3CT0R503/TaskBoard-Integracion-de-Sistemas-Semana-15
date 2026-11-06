<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Reglas del formulario de registro.
 *
 * Va en una clase aparte por la misma razón que las transacciones:
 * el controlador se queda con el flujo (crear el usuario, abrir la
 * sesión, redirigir) y las reglas viven donde se pueden leer de un
 * solo golpe.
 */
class RegistroRequest extends FormRequest
{
    /**
     * Quién puede enviar esta petición.
     *
     * Acá va true de verdad, no por descuido: registrarse es lo
     * único que tiene sentido permitirle a cualquiera que todavía
     * no tiene cuenta. La puerta la cierra el middleware guest de
     * la ruta, que es quien impide que alguien ya logueado vuelva
     * al formulario.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de cada campo.
     *
     * unique:users,email es la que de verdad protege la tabla. La
     * columna ya tiene índice único, así que sin esta regla el
     * segundo registro con el mismo correo no entraría igual, pero
     * el usuario vería un error de base de datos en vez de un
     * mensaje en español junto al campo.
     *
     * confirmed espera un segundo campo llamado
     * password_confirmation. El nombre no es opcional: la regla lo
     * busca con ese sufijo exacto.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * Mensajes propios, para que el error diga qué corregir en
     * lugar de nombrar la regla que falló.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Debes indicar tu nombre.',
            'email.required' => 'Debes indicar un correo.',
            'email.email' => 'Ese correo no tiene un formato válido.',
            'email.unique' => 'Ese correo ya está registrado.',
            'password.required' => 'Debes indicar una contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ];
    }

    /**
     * Nombres legibles para los mensajes que dejé por defecto.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo',
            'password' => 'contraseña',
        ];
    }
}
