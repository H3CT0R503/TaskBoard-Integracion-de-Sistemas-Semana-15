{{-- Panel de un comercio: sus datos y todas sus transacciones --}}
@extends('layouts.app')

@section('titulo', $comercio->nombre_comercio)

@section('contenido')
    <p><a href="{{ route('comercios.index') }}">&larr; Volver a comercios</a></p>

    <h1>{{ $comercio->nombre_comercio }}</h1>

    {{-- A route() le paso el modelo completo y él saca el id solo,
         sin escribir $comercio->id --}}
    <a href="{{ route('transacciones.create', $comercio) }}">
        + Nueva transacción
    </a>

    <p class="meta">
        Rubro: {{ $comercio->rubro }}
        &middot;
        Teléfono: {{ $comercio->telefono ?? 'Sin teléfono registrado' }}
    </p>

    {{-- Antes esto era un @if de tres ramas dentro de la vista.
         Como componente, la frase del resumen se arregla en un solo
         archivo --}}
    <x-resumen-actividad :total="$comercio->transacciones->count()" />

    <h2>Transacciones</h2>

    @forelse ($comercio->transacciones as $transaccion)
        <div class="card">
            <strong>${{ number_format($transaccion->monto, 2) }}</strong>
            — {{ $transaccion->cliente_nombre }}
            <x-badge-estado :estado="$transaccion->estado" />

            <div class="acciones">
                <a href="{{ route('transacciones.edit', $transaccion) }}">Editar</a>

                {{-- Cada transacción solo muestra los botones de las
                     transiciones que su estado actual permite: una
                     Iniciada puede cerrarse bien o mal, una Fallida se
                     reintenta, y una Completada ya no se mueve. El
                     controlador vuelve a revisar lo mismo, porque
                     esconder un botón no bloquea la petición --}}
                @if ($transaccion->estado === 'Iniciada')
                    <form action="{{ route('transacciones.mover', [$transaccion, 'Completada']) }}"
                          method="POST">
                        @csrf
                        @method('PATCH')
                        <button type="submit">Marcar Completada</button>
                    </form>

                    <form action="{{ route('transacciones.mover', [$transaccion, 'Fallida']) }}"
                          method="POST">
                        @csrf
                        @method('PATCH')
                        <button type="submit">Marcar Fallida</button>
                    </form>
                @elseif ($transaccion->estado === 'Fallida')
                    <form action="{{ route('transacciones.mover', [$transaccion, 'Iniciada']) }}"
                          method="POST">
                        @csrf
                        @method('PATCH')
                        <button type="submit">Reintentar</button>
                    </form>
                @endif

                {{-- Eliminar no tiene vuelta atrás, así que el envío
                     pasa por confirm(): si se cancela devuelve false y
                     el formulario nunca sale del navegador.

                     El botón se muestra en todas las transacciones a
                     propósito, incluidas las Completadas. Si lo
                     escondiera, el usuario se quedaría sin saber por
                     qué no puede borrarlas; así lo intenta y recibe el
                     motivo por escrito. Quien decide es destroy() --}}
                <form action="{{ route('transacciones.destroy', $transaccion) }}"
                      method="POST"
                      onsubmit="return confirm('¿Eliminar esta transacción? Esta acción no se puede deshacer.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="peligro">Eliminar</button>
                </form>
            </div>
        </div>
    @empty
        <p>Sin transacciones</p>
    @endforelse
@endsection