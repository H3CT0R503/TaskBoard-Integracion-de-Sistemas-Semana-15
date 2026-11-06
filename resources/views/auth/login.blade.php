{{-- Formulario de entrada --}}
@extends('layouts.app')

@section('titulo', 'Iniciar sesión')

@section('contenido')
    <h1>Iniciar sesión</h1>
    <p class="meta">Entra con el correo y la contraseña de tu cuenta.</p>

    {{-- El controlador devuelve el error con
         withErrors(['email' => ...]), así que el único mensaje que
         llega acá es el genérico de credenciales --}}
    @if ($errors->any())
        <div class="resumen-errores">
            <strong>No pudimos iniciar tu sesión:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- El login también lleva @csrf. Que no cree nada en la base
         no lo vuelve inofensivo: sin token, otra página podría
         enviar este formulario con credenciales que ella controle
         y dejar a la víctima dentro de una cuenta ajena --}}
    <form action="{{ route('login.attempt') }}" method="POST" class="card formulario">
        @csrf

        <label for="email">Correo</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autofocus>
        @error('email')
            <span class="error">{{ $message }}</span>
        @enderror

        {{-- Nada de old() acá: la contraseña se escribe de nuevo
             cada vez que el intento falla --}}
        <label for="password">Contraseña</label>
        <input id="password" name="password" type="password">
        @error('password')
            <span class="error">{{ $message }}</span>
        @enderror

        {{-- Este checkbox lo lee el controlador con
             $request->boolean('recordar') y lo pasa como segundo
             argumento de Auth::attempt() --}}
        <label class="casilla">
            <input type="checkbox" name="recordar" value="1" @checked(old('recordar'))>
            Recordarme
        </label>

        <button type="submit">Entrar</button>
    </form>

    <p class="meta">
        ¿Todavía no tienes cuenta?
        <a href="{{ route('registro') }}">Crear una</a>
    </p>
@endsection
