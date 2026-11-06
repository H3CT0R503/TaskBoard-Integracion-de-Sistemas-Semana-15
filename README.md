# 🚀 Guías de Prácticas Laravel - TaskBoard: Pasarela de Pagos

![Laravel](https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Bruno](https://img.shields.io/badge/Bruno-F4AA41?style=for-the-badge&logo=bruno&logoColor=white)
![Postman](https://img.shields.io/badge/Postman-FF6C37?style=for-the-badge&logo=postman&logoColor=white)
![PHPUnit](https://img.shields.io/badge/PHPUnit-366488?style=for-the-badge&logo=php&logoColor=white)
![Git](https://img.shields.io/badge/Git-F05032?style=for-the-badge&logo=git&logoColor=white)

---

<p align="center">
  <img src="https://res.cloudinary.com/bcwlyire/image/upload/v1785118882/NUEVO-LOGO-UPED-A-COLOR-scaled_szoqws.jpg" alt="Logo UPED" width="300"/>
</p>

## 🏛️ Información Académica

- **Institución:** Universidad Pedagógica de El Salvador *Luis Alonso Aparicio*
- **Facultad:** Ingeniería
- **Asignatura:** Integración de Sistemas
- **Ciclo:** 02-2026
- **Docente:** Ing. Oscar Contreras
- **Proyecto Integrador:** TaskBoard - Pasarela de Pagos

---

## 📌 Descripción del Proyecto

Este repositorio contiene el trabajo de la **Semana 15** del proyecto **TaskBoard**, una plataforma de **Pasarela de Pagos** desarrollada en el framework **Laravel 12**.

Las semanas anteriores construyeron la API y le pusieron cerradura. Esta semana no se construye nada nuevo: se busca dónde está mal. **El jueves se diseñan casos límite para encontrar errores, el viernes se corrigen y se demuestra con las mismas pruebas que quedaron resueltos.**

Son **dos guías y una sola entrega**:

**Guía N.° 15 — Semana 15 · Jueves: ¿Cómo se prueba una API? Estrategias y casos límite**

1. Diseñar casos con tres técnicas: **partición de equivalencia**, **valores límite** y **casos negativos**.
2. Ejecutarlos en Bruno con pruebas automáticas, escribiendo el resultado esperado **antes** de pulsar Send.
3. Registrar los 15 casos en una bitácora y reportar los errores con sus **seis partes** y su severidad.
4. Comprobar la **idempotencia** del DELETE con un experimento dirigido.
5. (Extra) Escribir la primera prueba de **PHPUnit**.

**Guía N.° 16 — Semana 15 · Viernes: Manejo de errores e integridad de los datos**

1. Corregir los errores de la bitácora y demostrarlo con una **prueba de regresión**.
2. Configurar respuestas de error **uniformes y seguras** en `bootstrap/app.php`.
3. Comprobar con un experimento cómo **`DB::transaction()`** evita registros a medias.
4. (Extra) Proteger cada corrección con una prueba de PHPUnit.

> 🎯 **La regla de oro del jueves.** El resultado esperado se escribe en la bitácora **ANTES** de pulsar Send. Si se escribe después de ver la respuesta, la prueba siempre "pasa" y no prueba nada.

> 📄 Las respuestas de análisis, las tablas de los experimentos y las dos bitácoras de aprendizaje están en **[RESPUESTAS_SEMANA15.md](RESPUESTAS_SEMANA15.md)**. La bitácora de pruebas con los 15 casos y los 5 reportes de error está en **[Bitacora_Pruebas_Semana15_Interiano.xlsx](Bitacora_Pruebas_Semana15_Interiano.xlsx)**.

### 🐛 Lo que se encontró, y lo que se corrigió

| # | Caso | Qué pasaba el jueves | Severidad | Corrección del viernes |
|---|---|---|---|---|
| **E-01** | CL-07 · `monto: 100000000` | **500** `Numeric value out of range`. El dato era del cliente y la API echaba la culpa al servidor | Alta | Regla `max:999999.99` |
| **E-02** | CL-09 · `monto: 25.999` | **201**, y guardaba **26.00**. La base redondeaba dinero en silencio | Alta | Regla `decimal:0,2` |
| **E-03** | CL-12 · `"estado": "Completada"` | **201**, y descartaba el campo sin avisar: el cliente creía haber creado una transacción Completada | Media | Regla `'estado' => ['prohibited']` |
| **E-04** | CL-10 · nombre de 256 | **422** correcto, pero con el texto `validation.max.string` sin traducir | Baja | Mensaje `cliente_nombre.max` |
| **E-05** | CL-13 · JSON con coma de más | **422** "faltan comercio_id, cliente_nombre y monto". Los tres venían | Media | Middleware `ValidarCuerpoJson` → **400** |
| **⚠** | CL-14 · sin `Content-Type` | **422** con el mismo mensaje engañoso. El jueves quedó como *contrato ambiguo* | Media | Contrato decidido: **415** |

### 📐 Dos diferencias con la guía

1. La guía supone que la columna `monto` es `decimal(10,2)`, con tope 99 999 999.99. En este proyecto es **`decimal(8,2)`**, así que el tope real es **999 999.99** y esa es la cota que usa la validación. El caso CL-07 sigue siendo válido: 100 000 000 está muy por encima de los dos topes.
2. La guía nombra el Form Request como `StoreTransaccionRequest`. Aquí se llama **`GuardarTransaccionRequest`**, porque desde la Semana 10 lo comparten `store()` y `update()` de las dos puertas.

---

## 📁 Estructura del Proyecto

```text
taskboard/
├── app/
│   └── Http/
│       ├── Controllers/Api/
│       │   └── TransaccionApiController.php          # MODIFICADO  DB::transaction() + evento
│       ├── Middleware/
│       │   └── ValidarCuerpoJson.php                 # NUEVO  400 y 415 antes de validar
│       └── Requests/
│           └── GuardarTransaccionRequest.php         # MODIFICADO  max, decimal, prohibited
├── bootstrap/
│   └── app.php                                       # MODIFICADO  shouldRenderJsonWhen + QueryException
├── routes/
│   └── api.php                                       # MODIFICADO  ruta temporal del Lab 4 (ya borrada)
├── tests/Feature/
│   ├── CrearTransaccionTest.php                      # NUEVO  11 pruebas de regresión
│   └── ExampleTest.php                               # MODIFICADO  estaba en rojo desde la Semana 12
├── TaskBoard_CasosLimite_S15/                        # NUEVO  colección de Bruno, 17 casos
├── TaskBoard-API-Semana15.postman_collection.json    # NUEVO  los mismos casos, en Postman
├── Bitacora_Pruebas_Semana15_Interiano.xlsx          # NUEVO  bitácora y reportes de error
└── RESPUESTAS_SEMANA15.md                            # NUEVO  respuestas y bitácoras
```

---

## ⚙️ Puesta en marcha

El proyecto corre sobre **XAMPP** (Apache + MySQL) con la base de datos `taskboard_15`.

### 1. Crear la base de datos

```sql
CREATE DATABASE taskboard_15 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Preparar el entorno

```bash
composer install
cp .env.example .env
php artisan key:generate
```

| Variable | Valor |
|---|---|
| `DB_DATABASE` | `taskboard_15` |
| `DB_USERNAME` | `root` |
| `DB_PASSWORD` | *(vacío, usuario por defecto de XAMPP)* |

### 3. Crear las tablas y los datos de ejemplo

```bash
php artisan migrate:fresh --seed
php artisan serve
```

### 4. Usuarios y datos de prueba

| Usuario | Correo | Contraseña | Comercios |
|---|---|---|---|
| Ana López | `ana@taskboard.test` | `password123` | 1 Café Amanecer, 2 Pupusería Doña Marta |
| Carlos Pérez | `carlos@taskboard.test` | `password123` | 3 Ferretería El Tornillo, 4 Librería La Página |

---

## 🔌 Códigos de error de la API

La API sigue teniendo las 12 rutas de la Semana 14 (una pública y once dentro de `auth:sanctum`). Lo que cambió esta semana son **las respuestas cuando algo sale mal**:

| Código | Qué significa | Cuándo sale ahora |
|---|---|---|
| **400** Bad Request | No pude leer tu petición | El cuerpo dice ser JSON y no se puede parsear *(nuevo)* |
| **401** Unauthorized | No sé quién eres | Sin token, token vencido o revocado |
| **404** Not Found | No existe para ti | Un id que no existe, o que es de otro usuario |
| **415** Unsupported Media Type | No sé en qué formato me lo mandas | Cuerpo sin `Content-Type: application/json` *(nuevo)* |
| **422** Unprocessable Content | Te entendí, pero los datos no sirven | Validación: monto, nombre, comercio, estado |
| **429** Too Many Requests | Demasiados intentos | 6 logins en un minuto |
| **500** Internal Server Error | Fue culpa mía | Falla de base de datos. **Mensaje genérico**, detalle solo en el log *(nuevo)* |

**Los tres códigos que antes se confundían en uno.** Hasta el jueves, un JSON mal escrito, un cuerpo sin `Content-Type` y una petición a la que de verdad le faltan campos respondían los tres **422 con el mismo mensaje**. Ahora cada uno responde lo suyo, y eso es lo que permite que quien consume la API sepa qué arreglar.

---

## 🧪 Cómo probar

Van **tres formas** en el repositorio, con los mismos casos.

### Opción A — Bruno (la que usa la guía)

La carpeta **`TaskBoard_CasosLimite_S15/`**, que se abre con *Open Collection* (**no** *Import*) apuntando a esa carpeta.

```text
TaskBoard · Casos límite (Semana 15)
├── 0. Preparación   Login Ana → guarda {{tokenAna}}
├── 1. Resueltos     CL-02 (pasa) · CL-07 (el jueves falla)
├── 2. Semiarmados   CL-01 · CL-03 · CL-04 · CL-05 · CL-09
├── 3. Desde cero    CL-06 · CL-08 · CL-10 a CL-17 (+ LEEME)
└── 4. Limpieza      borra lo que crearon CL-01, CL-08, CL-16 y CL-17
```

**Hay que correr primero `0. Preparación / Login Ana`**: ahí se guarda el token que el resto hereda como Bearer.

> 🧹 **Por qué hay una carpeta de limpieza.** Cuatro casos crean transacciones de verdad. Sin borrarlas, cada corrida dejaría cuatro filas nuevas en la base y los conteos del experimento de integridad se irían moviendo entre una corrida y la siguiente.

### Opción B — Postman

**`TaskBoard-API-Semana15.postman_collection.json`**, formato v2.1 (Insomnia también lo importa). Trae las carpetas de la Semana 14 como prueba de regresión **más** la carpeta `4. Casos límite` con los mismos 17 casos:

```text
0. Públicas · 1. Ana · 2. Carlos · 3. Sin token   ← regresión de la Semana 14
4. Casos límite · 5. Limpieza                     ← lo nuevo de la Semana 15
45 peticiones · 101 comprobaciones automáticas
```

> ⏱️ `/api/login` tiene `throttle:5,1` y la colección hace 4 logins: correrla dos veces dentro del mismo minuto devuelve **429** en los últimos. No es un error, es la defensa funcionando.

### Opción C — PHPUnit

```bash
php artisan test
```

```text
PASS  Tests\Feature\CrearTransaccionTest
✓ cl02 monto cero responde 422          ✓ e04 el mensaje de nombre largo esta traducido
✓ cl01 monto minimo valido responde 201 ✓ e05 json mal formado responde 400
✓ e01 monto sobre el maximo             ✓ cl14 cuerpo sin content type responde 415
✓ e02 tres decimales                    ✓ crear registra el evento de creacion
✓ la correccion de e02 no rompe los…    ✓ no se puede crear en el comercio de otro
✓ e03 estado prohibido al crear

PASS  Tests\Feature\ExampleTest
✓ la raiz manda al login a quien no tiene sesion

Tests:  13 passed (30 assertions)   Duration: 1.23s
```

No necesita servidor ni base de datos encendida: Laravel arma la petición por dentro y usa SQLite en memoria, que nace limpia en cada prueba.

---

## 🧠 Decisiones de diseño

### El límite va en la validación, no en la columna

```php
'monto' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999.99'],
```

Para E-01 se podría haber agrandado la columna a `decimal(15,2)`. **No resuelve el error: lo mueve.** El borde seguiría existiendo más arriba y un monto de un billón volvería a dar el mismo 500, solo que con un valor tan grande que nadie lo probaría por casualidad.

Y responde la pregunta equivocada. La pregunta no es *"¿cuánto cabe?"* sino *"¿cuál es un monto válido para esta pasarela?"*, que es una decisión de negocio: un cobro de cien millones en una pasarela que atiende pupuserías no es un dato que no quepa, es un dato que está mal.

> La columna es la última red, no la regla. Validando contesta la aplicación, que puede decir 422 y explicar el límite; dejándoselo a la columna contesta el motor, que solo sabe reventar — y **contesta distinto según el motor**: el mismo caso que en MySQL da 500, en SQLite da 201 y guarda el valor.

### `decimal:0,2` y no `decimal:2`

Con un solo número exige **exactamente** dos decimales, y eso rechazaría `25` y `25.5`, que son montos válidos. El riesgo está cubierto con una prueba propia, `test_la_correccion_de_e02_no_rompe_los_montos_normales`.

### El cuerpo se revisa antes de validar

```php
$middleware->appendToGroup('api', [ValidarCuerpoJson::class]);
```

E-05 y CL-14 tenían el mismo síntoma — *"faltan los tres campos"* cuando los tres venían — porque Laravel solo convierte el cuerpo en datos cuando puede: con el JSON mal escrito no puede, y sin `Content-Type` ni lo intenta. En los dos casos `$request->all()` queda vacío y la validación reclama lo obligatorio.

Tiene que ser un **middleware** y no una regla: para cuando la validación entra en escena, el cuerpo ya se intentó leer y el daño ya está hecho.

### El detalle del error no desaparece, cambia de destinatario

```php
$exceptions->shouldRenderJsonWhen(fn (Request $r, Throwable $e) => $r->is('api/*') || $r->expectsJson());

$exceptions->render(function (QueryException $e, Request $request) {
    if ($request->is('api/*')) {
        return response()->json(['message' => 'No se pudo guardar la información. Intenta de nuevo más tarde.'], 500);
    }
});
```

Antes, una falla de base de datos respondía con la consulta SQL completa, el nombre de la base, el host, el puerto y las rutas absolutas del servidor. Ahora el cliente recibe una frase y **el log recibe todo**, que es donde hace falta y donde solo entra quien tiene acceso al servidor.

`shouldRenderJsonWhen` hace que `/api` responda JSON aunque falte el `Accept`. Eso no vuelve al encabezado innecesario: sigue siendo la forma en que el cliente dice qué entiende, y un proxy o un gateway delante de esta API decidirían mirando el encabezado, no este archivo.

### `DB::transaction()`: todo o nada

Crear la transacción y registrar su evento son dos INSERT en dos tablas, y entre uno y otro puede fallar cualquier cosa. Medido:

| Momento | `Transaccion::count()` | `EventoTransaccion::count()` |
|---|---|---|
| Antes | 4 | 0 |
| Sin `DB::transaction` | **5** | **0** |
| Con `DB::transaction` | **4** | **0** |

Las dos veces el cliente recibió un 500. Sin la transacción quedó **un cobro sin su evento de creación**: una fila perfectamente válida, que pasa cualquier revisión, que no aparece en ningún reporte armado desde los eventos y que nadie sabe que está ahí, porque el cliente vio un error y dio por hecho que no se guardó nada.

> El 500 miente por omisión: dice "falló" y uno entiende "no pasó nada", cuando en realidad pasó la mitad.

---

## ✅ Verificación

### Prueba de regresión

| | Pasan | Fallan |
|---|---|---|
| Línea base (código del jueves) | 11 | **6** — CL-07, CL-09, CL-10, CL-12, CL-13, CL-14 |
| Después de corregir | **17** | 0 |

Esos números son **casos** de la bitácora. El Runner de Bruno cuenta **peticiones**, y la colección tiene 25 (los 17 casos más el login, el LEEME, los tres pasos de CL-15 por separado y las cuatro de limpieza), así que ahí la línea base se ve como 19 en verde y 6 en rojo. Los 6 en rojo son los mismos.

La colección quedó completa en verde **sin tocar una sola prueba**. Si se hubiera cambiado el esperado para que pasaran, no habría demostrado nada.

### Una prueba que pasaba y empezó a fallar

Al correr también la colección de la Semana 14 aparecieron dos pruebas en rojo, las de la petición `3.2`, que afirmaban que una petición sin `Accept` **no** devolvía JSON.

La guía dice que, si algo que pasaba ahora falla, no se cambia la prueba: se revisa la regla. Se revisó, y el caso resultó ser el otro: **la regla está bien y la prueba afirmaba un comportamiento que se cambió a propósito** con `shouldRenderJsonWhen`. Esas dos pruebas se reescribieron, con la explicación dentro de la propia colección.

> Una prueba en rojo puede significar "rompiste algo" o "cambiaste algo a propósito y la prueba no se enteró". Confundirlos es como se cuelan los errores; lo que nunca es válido es cambiar la prueba hasta que pase sin entender cuál de las dos cosas ocurrió.

### El reto del extra

Quitando `max:999999.99` y corriendo `php artisan test`, se pone en rojo **exactamente una** prueba:

```text
⨯ e01 monto sobre el maximo
Tests:  1 failed, 10 passed
```

Las otras diez siguen en verde. Eso es lo que hace útil a una prueba de regresión: no avisa solo que algo se rompió, sino **qué** se rompió.

---

## 📚 Semanas anteriores

- **Semana 13** — servicios web REST, JSON y API Resources
- **Semana 14** — autenticación de API con Laravel Sanctum ([RESPUESTAS_SEMANA14.md](RESPUESTAS_SEMANA14.md))
- **Semana 15** — casos límite, manejo de errores e integridad ([RESPUESTAS_SEMANA15.md](RESPUESTAS_SEMANA15.md))
