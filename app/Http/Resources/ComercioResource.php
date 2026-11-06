<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * La forma pública de un comercio.
 *
 * Esta clase es el contrato de la API. Devolver el modelo directo
 * funcionaba, pero ataba la respuesta a las columnas de la tabla:
 * agregar una columna la publicaba sin que nadie lo decidiera, y
 * renombrarla rompía a todos los clientes de golpe. Acá la tabla
 * puede cambiar sin que la respuesta cambie, porque la traducción
 * de una a otra vive en este archivo.
 */
class ComercioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // La columna se llama nombre_comercio y afuera sale como
            // 'nombre'. El nombre interno puede cambiar mañana sin
            // tocar a quien ya consume la API.
            'nombre' => $this->nombre_comercio,
            'rubro' => $this->rubro,

            // user_id, created_at y updated_at NO salen. El dueño es
            // información interna: decir a qué usuario pertenece cada
            // comercio permitiría agrupar comercios por persona desde
            // afuera, y eso no se le debe a ningún cliente.

            // whenLoaded() es la pieza que evita el problema N+1: la
            // clave aparece solo si la relación venía cargada con
            // load() o with(). En el listado no se carga, así que la
            // clave ni se asoma y no se dispara una consulta por fila;
            // en el detalle sí, porque ahí se pidió explícitamente.
            'transacciones' => TransaccionResource::collection(
                $this->whenLoaded('transacciones')
            ),
        ];
    }
}
