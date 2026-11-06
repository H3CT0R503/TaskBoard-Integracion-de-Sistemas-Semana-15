{{-- Marca de un golpe si el comercio ya operó o sigue sin mover
     nada. Recibe el conteo que withCount() dejó listo, no la
     colección, para no recorrerla otra vez en la vista.
     Uso: <x-badge-actividad :totalTransacciones="$comercio->transacciones_count" /> --}}
@props(['totalTransacciones'])

@if ($totalTransacciones == 0)
    <span class="badge gris">Sin actividad</span>
@else
    <span class="badge verde">Activo</span>
@endif
