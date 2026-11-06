{{-- Formulario de edición, con los datos actuales ya cargados --}}
@extends('layouts.app')
@section('titulo', 'Editar transacción')

@section('contenido')
    <p><a href="{{ route('comercios.show', $transaccion->comercio_id) }}">&larr; Volver al comercio</a></p>

    <h1>Editar transacción</h1>

    {{-- Mismo resumen de errores del formulario de registro: las
         reglas son las mismas porque comparten el Form Request --}}
    @if ($errors->any())
        <div class="resumen-errores">
            <strong>Revisa estos datos antes de continuar:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- El method del form dice POST, pero @method('PUT') agrega un
         campo oculto con el verbo real y Laravel enruta con ese. Sin
         eso, el navegador nunca podría llamar a una ruta PUT --}}
    <form action="{{ route('transacciones.update', $transaccion) }}" method="POST">
        @csrf
        @method('PUT')

        {{-- Acá old() lleva dos argumentos y el orden es el truco: de
             primero lo que se acababa de escribir cuando la validación
             rebotó, y de segundo el valor guardado, que es lo que se ve
             al abrir el formulario por primera vez. Con un solo
             argumento los campos saldrían vacíos --}}
        <label for="comercio">Comercio</label>
        <select id="comercio" name="comercio_id">
            @foreach ($comercios as $c)
                <option value="{{ $c->id }}"
                    @selected(old('comercio_id', $transaccion->comercio_id) == $c->id)>
                    {{ $c->nombre_comercio }}
                </option>
            @endforeach
        </select>
        @error('comercio_id')
            <span class="error">{{ $message }}</span>
        @enderror
        <br>

        <label for="cliente">Cliente</label>
        <input id="cliente" name="cliente_nombre" type="text"
               value="{{ old('cliente_nombre', $transaccion->cliente_nombre) }}">
        @error('cliente_nombre')
            <span class="error">{{ $message }}</span>
        @enderror
        <br>

        <label for="monto">Monto</label>
        <input id="monto" name="monto" type="number" step="0.01"
               value="{{ old('monto', $transaccion->monto) }}">
        @error('monto')
            <span class="error">{{ $message }}</span>
        @enderror
        <br>

        <button type="submit">Guardar cambios</button>
    </form>
@endsection
