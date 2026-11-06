@props(['total' => 0])

@if ($total === 0)
    <p class="meta">Este comercio es nuevo, aún no registra actividad.</p>
@elseif ($total === 1)
    <p class="meta">Este comercio tiene su primera transacción registrada.</p>
@else
    <p class="meta">Este comercio tiene un historial de {{ $total }} transacciones.</p>
@endif