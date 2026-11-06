<!DOCTYPE html>
{{-- Plantilla base del sitio. Cada vista la hereda con
     @extends('layouts.app') y solo rellena @section('contenido'),
     así el nav, los estilos y el pie se escriben una sola vez --}}
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Pasarela de Pagos — TaskBoard')</title>

    <style>
        :root {
            --navy: #0B2E59;
            --gold: #F2A900;
            --ink: #13233D;
            --gray: #5B6B85;
            --line: #D7E1EF;
            --bg: #F5F8FC;
            --green: #1E7E52;
            --red: #C23B32;
        }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, "Segoe UI", Calibri, Arial, sans-serif;
            background: var(--bg);
            color: var(--ink);
            margin: 0;
        }
        nav {
            background: var(--navy);
            color: #fff;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        nav a {
            color: #fff;
            text-decoration: none;
            font-weight: bold;
        }
        nav .tag {
            background: var(--gold);
            color: var(--navy);
            padding: 0.2rem 0.7rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        main {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        h1 { color: var(--navy); }
        h2 { color: var(--navy); font-size: 1.1rem; margin-top: 2rem; }
        .card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 0.75rem;
        }
        .card a { color: var(--navy); font-weight: bold; text-decoration: none; }
        .card a:hover { text-decoration: underline; }
        .meta { color: var(--gray); font-size: 0.9rem; }
        .badge {
            display: inline-block;
            padding: 0.2rem 0.7rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: bold;
            color: #fff;
        }
        .badge.verde { background: var(--green); }
        .badge.rojo { background: var(--red); }
        .badge.amarillo { background: var(--gold); color: var(--navy); }
        .badge.gris { background: #9AAAC4; }
        footer {
            text-align: center;
            color: var(--gray);
            font-size: 0.85rem;
            padding: 2rem 0;
        }
        ul.comercios { list-style: none; padding: 0; }

        /* Errores de validación: el texto junto al campo y el
           resumen de arriba del formulario */
        .error {
            color: var(--red);
            font-size: 0.85rem;
            margin-left: 0.5rem;
        }
        .resumen-errores {
            background: #FBEAE9;
            border: 1px solid var(--red);
            color: var(--red);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin: 1rem 0;
        }
        .resumen-errores ul { margin: 0.5rem 0 0; padding-left: 1.2rem; }

        /* Fila de acciones de cada transacción: editar, mover
           estado y eliminar, en línea */
        .acciones {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.6rem;
        }
        .acciones form { margin: 0; }
        .acciones button {
            font: inherit;
            font-size: 0.8rem;
            padding: 0.25rem 0.7rem;
            border: 1px solid var(--line);
            background: #fff;
            border-radius: 6px;
            cursor: pointer;
        }
        .acciones button:hover { background: var(--bg); }
        .acciones button.peligro { color: var(--red); border-color: var(--red); }
        .acciones a { font-size: 0.8rem; color: var(--navy); }

        /* Lado derecho del nav: nombre del usuario, boton de salir
           o los enlaces de entrar y registrarse */
        nav .sesion {
            display: flex;
            align-items: center;
            gap: 0.9rem;
            font-size: 0.85rem;
        }
        nav .sesion form { margin: 0; }
        nav .sesion .hola { font-weight: normal; }
        nav .sesion button {
            font: inherit;
            font-size: 0.8rem;
            padding: 0.25rem 0.8rem;
            border: 1px solid rgba(255, 255, 255, 0.45);
            background: transparent;
            color: #fff;
            border-radius: 6px;
            cursor: pointer;
        }
        nav .sesion button:hover { background: rgba(255, 255, 255, 0.12); }

        /* Formularios de registro y login: una columna, para que el
           campo y su error queden uno debajo del otro */
        .formulario { max-width: 420px; }
        .formulario label {
            display: block;
            margin-top: 0.8rem;
            font-size: 0.85rem;
            font-weight: bold;
            color: var(--navy);
        }
        .formulario input[type="text"],
        .formulario input[type="email"],
        .formulario input[type="password"] {
            width: 100%;
            font: inherit;
            padding: 0.45rem 0.6rem;
            border: 1px solid var(--line);
            border-radius: 6px;
        }
        .formulario label.casilla {
            font-weight: normal;
            color: var(--ink);
        }
        .formulario label.casilla input { width: auto; margin-right: 0.3rem; }
        .formulario .error { display: block; margin: 0.25rem 0 0; }
        .formulario button {
            font: inherit;
            margin-top: 1.1rem;
            padding: 0.45rem 1.1rem;
            background: var(--navy);
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .formulario button:hover { background: #092343; }
    </style>
</head>
<body>
    {{-- Barra superior. @auth y @guest son el atajo de Blade para
         if (Auth::check()) y su contrario, así que las dos ramas
         nunca se muestran a la vez: o hay sesión, o no hay --}}
    <nav>
        <a href="{{ route('comercios.index') }}">Pasarela de Pagos · TaskBoard</a>

        <div class="sesion">
            @auth
                {{-- Auth::user() devuelve el modelo User completo del
                     que abrió sesión. Si hiciera falta solo el
                     identificador, Auth::id() lo da sin traer la fila --}}
                <span class="hola">Hola, {{ Auth::user()->name }}</span>

                {{-- Salir es un formulario y no un enlace: con un GET,
                     una imagen oculta en otra página bastaría para
                     cerrarle la sesión a quien la visitara --}}
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit">Salir</button>
                </form>
            @endauth

            @guest
                <a href="{{ route('login') }}">Entrar</a>
                <a href="{{ route('registro') }}">Registrarse</a>
            @endguest

            <span class="tag">UPED · Integración de Sistemas</span>
        </div>
    </nav>

    {{-- Mensajes flash de los redirects del controlador. Viven un
         solo request: al refrescar ya no están, que es justo lo que se
         quiere de un aviso de "se guardó" --}}
    @if (session('mensaje'))
        <div class="alerta-exito" style="background:#EAF6EE;
                color:#1E7E52; padding:0.75rem 1rem; margin:1rem auto;
                max-width:900px; border-radius:8px;">
            {{ session('mensaje') }}
        </div>
    @endif

    {{-- Canal aparte para lo que el sistema no dejó hacer. Va en rojo
         porque no es lo mismo avisar que algo se guardó que avisar que
         una acción fue rechazada, y el color lo dice antes de leer --}}
    @if (session('error'))
        <div class="alerta-error" style="background:#FBEAE9;
                color:#C23B32; padding:0.75rem 1rem; margin:1rem auto;
                max-width:900px; border-radius:8px;">
            {{ session('error') }}
        </div>
    @endif

    <main>
        @yield('contenido')
    </main>

    <footer>
        &copy; {{ date('Y') }} UPED — Integración de Sistemas
    </footer>
</body>
</html>
