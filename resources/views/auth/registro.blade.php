{{-- Formulario de alta de una cuenta nueva --}}
@extends('layouts.app')

@section('titulo', 'Crear cuenta')

@section('contenido')
    <h1>Crear cuenta</h1>
    <p class="meta">
        Registra tu usuario para administrar tus propios comercios en la pasarela.
    </p>

    {{-- Mismo resumen de errores que usan los formularios de
         transacciones: arriba la lista completa, y abajo cada
         mensaje junto al campo que lo provocó --}}
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

    <form action="{{ route('registro.store') }}" method="POST" class="card formulario">
        {{-- Sin @csrf la petición vuelve con 419. El token prueba
             que el formulario salió de este sitio y no de una
             página ajena que lo envió por mí --}}
        @csrf

        <label for="name">Nombre</label>
        <input id="name" name="name" type="text" value="{{ old('name') }}" autofocus>
        @error('name')
            <span class="error">{{ $message }}</span>
        @enderror

        <label for="email">Correo</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}">
        @error('email')
            <span class="error">{{ $message }}</span>
        @enderror

        {{-- old() aparece en el nombre y en el correo, pero NO en
             las contraseñas. Un campo de clave repoblado dejaría la
             contraseña escrita en el HTML que vuelve del servidor --}}
        <label for="password">Contraseña</label>
        <input id="password" name="password" type="password">
        @error('password')
            <span class="error">{{ $message }}</span>
        @enderror

        {{-- El nombre password_confirmation no es decorativo: es el
             que busca la regla confirmed del Form Request --}}
        <label for="password_confirmation">Confirmar contraseña</label>
        <input id="password_confirmation" name="password_confirmation" type="password">

        <button type="submit">Crear cuenta</button>
    </form>

    <p class="meta">
        ¿Ya tienes cuenta?
        <a href="{{ route('login') }}">Inicia sesión</a>
    </p>
@endsection
