<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * La forma pública de una transacción.
 *
 * Igual que el de comercios: traduce los nombres internos a los
 * públicos y deja fuera lo que el cliente no necesita.
 */
class TransaccionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // comercio_id entra esta semana, y conviene justificarlo
            // porque la semana pasada el criterio fue sacar los ids
            // internos de la respuesta.
            //
            // El que se sacó fue user_id, y por un motivo concreto:
            // decía a qué persona pertenecía cada fila en una API
            // donde cualquiera veía todas las filas, así que servía
            // para agrupar comercios por dueño desde afuera. Con
            // comercio_id no pasa eso: ahora la API solo devuelve
            // transacciones del dueño del token, así que el único
            // comercio que puede aparecer acá ya es suyo y el dato no
            // revela nada que no tenga.
            //
            // Y hace falta. Sin él, un cliente que recibe la lista no
            // puede decir a cuál de sus comercios corresponde cada
            // cobro, y el panel del reto del viernes (contar cobros
            // Completados por comercio) sería imposible de armar sin
            // pedir el detalle de cada comercio uno por uno.
            'comercio_id' => $this->comercio_id,

            'cliente' => $this->cliente_nombre,

            // El cast decimal:2 del modelo devuelve el monto como
            // cadena ("25.50"), y así saldría al JSON entre comillas.
            // El (float) lo entrega como número, que es lo que un
            // programa espera para poder sumarlo sin convertirlo.
            // El formato con símbolo y separadores es decisión de
            // quien muestra el dato, no de quien lo entrega.
            'monto' => (float) $this->monto,

            'estado' => $this->estado,

            // ISO 8601 es el formato que cualquier lenguaje sabe leer
            // y que lleva la zona horaria dentro. Mandar "27/10/2026"
            // obligaría a cada cliente a adivinar si el primer número
            // es el día o el mes.
            'fecha' => $this->created_at->toIso8601String(),
        ];
    }
}
