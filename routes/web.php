<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComercioController;
use App\Http\Controllers\TransaccionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas web — TaskBoard (Pasarela de Pagos)
|--------------------------------------------------------------------------
| Cada ruta une una URL y un verbo HTTP con un método del controlador.
| Acá no se escribe lógica: el controlador coordina, la vista muestra y
| el modelo habla con la base de datos.
|
| Todas llevan ->name() para que las vistas usen route('nombre') en vez
| de la URL escrita a mano; si mañana cambia la dirección, cambia solo
| en este archivo.
|
| Desde esta semana el archivo tiene además dos grupos. El middleware
| es el guardia que revisa antes de que el controlador corra:
|
|   guest -> solo pasa quien NO tiene sesión (registro y login)
|   auth  -> solo pasa quien SÍ la tiene (todo el resto del sistema)
|
| La ruta de inicio queda fuera de los dos grupos: redirige al listado,
| y es el propio middleware auth del listado el que decide si manda al
| login o deja entrar.
*/

Route::get('/', function () {
    return redirect()->route('comercios.index');
});

/*
|--------------------------------------------------------------------------
| Puerta de entrada — solo para visitantes sin sesión
|--------------------------------------------------------------------------
| El middleware guest evita el caso raro de alguien ya logueado que
| vuelve a /login y abre una segunda sesión sobre la primera: lo
| devuelve al listado.
|
| El nombre 'login' de la cuarta ruta no se puede cambiar por gusto.
| Cuando el middleware auth bloquea a un visitante, redirige a
| route('login'); si esa ruta se llamara distinto, Laravel reventaría
| con "Route [login] not defined" en vez de mostrar el formulario.
*/
Route::middleware('guest')->group(function () {
    Route::get('/registro', [AuthController::class, 'mostrarRegistro'])
        ->name('registro');

    Route::post('/registro', [AuthController::class, 'registrar'])
        ->name('registro.store');

    Route::get('/login', [AuthController::class, 'mostrarLogin'])
        ->name('login');

    // throttle:intentos,minutos — el reto del viernes. Cinco envíos
    // por minuto desde la misma IP; el sexto recibe 429 sin llegar al
    // controlador, así que nadie puede probar contraseñas en serie.
    // Cuenta los envíos, no los fallos: un login correcto también
    // gasta uno de los cinco.
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.attempt');
});

/*
|--------------------------------------------------------------------------
| Sistema — solo con sesión abierta
|--------------------------------------------------------------------------
| Todas las rutas de comercios y transacciones se movieron acá dentro.
| Sin este grupo, cualquiera que conociera la URL /comercios/3 veía el
| panel completo de un comercio ajeno.
|
| Ojo con la diferencia que se trabaja el viernes: este middleware
| responde "quién eres", no "qué es tuyo". Que la sesión exista no
| vuelve propios los datos, y de eso se encargan los abort_unless de
| los controladores.
*/
Route::middleware('auth')->group(function () {
    // El logout vive acá y no en el grupo guest: solo tiene sentido
    // para quien tiene algo que cerrar. Va por POST para que el token
    // CSRF sea obligatorio.
    Route::post('/logout', [AuthController::class, 'logout'])
        ->name('logout');

    Route::get('/comercios', [ComercioController::class, 'index'])
        ->name('comercios.index');

    Route::get('/comercios/{comercio}', [ComercioController::class, 'show'])
        ->name('comercios.show');

    // El GET necesita el {comercio} para saber de quién es el formulario.
    // El POST no lo lleva en la URL porque ese dato viaja dentro del
    // formulario, en un campo oculto.
    Route::get('/comercios/{comercio}/transacciones/nueva',
        [TransaccionController::class, 'create']
    )->name('transacciones.create');

    Route::post('/transacciones',
        [TransaccionController::class, 'store']
    )->name('transacciones.store');

    // Editar y eliminar. Un formulario HTML solo sabe mandar GET y POST,
    // así que PUT y DELETE llegan simulados: @method() agrega un campo
    // oculto y Laravel enruta según ese valor, no según el verbo real.
    Route::get('/transacciones/{transaccion}/editar',
        [TransaccionController::class, 'edit']
    )->name('transacciones.edit');

    Route::put('/transacciones/{transaccion}',
        [TransaccionController::class, 'update']
    )->name('transacciones.update');

    Route::delete('/transacciones/{transaccion}',
        [TransaccionController::class, 'destroy']
    )->name('transacciones.destroy');

    // Cambiar el estado en un clic. Va con PATCH porque toca un solo
    // campo; PUT es para cuando se reemplaza el recurso completo, como en
    // el formulario de edición. El estado viaja en la URL, no en el
    // cuerpo, para que cada botón apunte a una dirección distinta.
    Route::patch('/transacciones/{transaccion}/mover/{estado}',
        [TransaccionController::class, 'moverEstado']
    )->name('transacciones.mover');
});

// Banco de pruebas de formularios. Queda aislado del CRUD y no
// escribe nada en la base de datos, así que no necesita sesión.
Route::get('/practica/formulario-demo', function () {
    return view('practica.formulario_demo');
});

Route::post('/practica/enviar', function () {
    return 'Formulario recibido correctamente.';
});

// Ruta de la prueba del Form Request en otro método. Ya la verifiqué,
// y la dejo comentada porque solo imprimía los datos validados.
// Route::post('/practica/duplicar',
//     [TransaccionController::class, 'duplicarUltima']
// )->name('practica.duplicar');
