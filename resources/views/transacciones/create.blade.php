{{-- Formulario de registro de una transacción nueva --}}
@extends('layouts.app')
@section('titulo', 'Nueva transacción')

@section('contenido')
    <p><a href="{{ route('comercios.show', $comercio) }}">&larr; Volver al comercio</a></p>

    <h1>Nueva transacción</h1>
    <p class="meta">Comercio: {{ $comercio->nombre_comercio }}</p>

    {{-- Resumen con todos los errores arriba del formulario, para
         no obligar a buscar cuál campo falló --}}
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

    <form action="{{ route('transacciones.store') }}" method="POST">
        @csrf
        {{-- El comercio no se elige acá: ya se sabe por la URL. Va
             oculto, y la regla exists del Form Request es la que impide
             que alguien edite el valor desde el navegador --}}
        <input type="hidden" name="comercio_id" value="{{ $comercio->id }}">

        {{-- old() devuelve lo que se había escrito: si la validación
             rebota, el formulario vuelve lleno y no desde cero --}}
        <label for="cliente">Cliente</label>
        <input id="cliente" name="cliente_nombre" type="text"
               value="{{ old('cliente_nombre') }}">
        @error('cliente_nombre')
            <span class="error">{{ $message }}</span>
        @enderror
        <br>

        <label for="monto">Monto</label>
        <input id="monto" name="monto" type="number" step="0.01"
               value="{{ old('monto') }}">
        @error('monto')
            <span class="error">{{ $message }}</span>
        @enderror
        <br>

        {{-- El campo del comercio está oculto, así que su error no
             tiene un input al lado donde mostrarse --}}
        @error('comercio_id')
            <span class="error">{{ $message }}</span><br>
        @enderror

        <button type="submit">Registrar</button>
    </form>
@endsection
