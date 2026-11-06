{{-- Listado de comercios con buscador y filtro por rubro --}}
@extends('layouts.app')

@section('titulo', 'Comercios afiliados')

@section('contenido')
    <h1>Comercios afiliados a la pasarela</h1>

    {{-- El formulario va por GET: la búsqueda no cambia nada en la
         base, y así el filtro queda guardado en la URL y se puede
         compartir. Por eso tampoco necesita @csrf. request('buscar')
         devuelve lo que se escribió, para que el campo no se vacíe al
         recargar la página --}}
    <form action="{{ route('comercios.index') }}" method="GET">
        <input type="text" name="buscar"
               placeholder="Buscar comercio..."
               value="{{ request('buscar') }}">

        {{-- Los rubros los arma el controlador con distinct() --}}
        <select name="rubro">
            <option value="">Todos los rubros</option>
            @foreach ($rubros as $rubro)
                <option value="{{ $rubro }}" @selected(request('rubro') === $rubro)>
                    {{ $rubro }}
                </option>
            @endforeach
        </select>

        <button type="submit">Buscar</button>
    </form>

    <ul class="comercios">
        @forelse ($comercios as $comercio)
            <li class="card">
                <a href="{{ route('comercios.show', $comercio) }}">
                    {{ $comercio->nombre_comercio }}
                </a>
                <span class="meta">— {{ $comercio->rubro }}
                    ({{ $comercio->transacciones_count }} transacciones)</span>
                <br>
                <x-badge-actividad :totalTransacciones="$comercio->transacciones_count" />
            </li>
        @empty
            <li class="card">Aún no hay comercios afiliados.</li>
        @endforelse
    </ul>
@endsection
