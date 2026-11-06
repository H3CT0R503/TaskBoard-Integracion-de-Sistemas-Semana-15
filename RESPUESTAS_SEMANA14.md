# Respuestas y bitacora — Semana 14

Guia Practica N.o 13: Autenticacion de API con Laravel Sanctum (jueves)
Guia Practica N.o 14: Probar, depurar y consumir la API protegida (viernes)

Alumno: Hector Alonso Interiano Figueroa
Asignatura: Integracion de Sistemas — CE-ISC019
Ciclo: 02-2026

Las dos guias son una sola entrega, como quedo indicado en clase, asi que este
archivo responde las dos.

Datos sembrados por el seeder, que son los que usan todas las pruebas:

| Comercio | Dueño | Transacciones |
|---|---|---|
| 1 Cafe Amanecer | Ana | 1 Completada, 2 Iniciada |
| 2 Pupuseria Doña Marta | Ana | 3 Fallida |
| 3 Ferreteria El Tornillo | Carlos | — |
| 4 Libreria La Pagina | Carlos | 4 Iniciada |

Usuarios: `ana@taskboard.test` y `carlos@taskboard.test`, los dos con la clave
`password123`.

**Una aclaracion sobre los numeros.** La guia usa el comercio 2 como ejemplo de
comercio ajeno a Ana y habla de "1 comercio de Carlos". En este proyecto el
seeder reparte distinto: Ana tiene los comercios 1 y 2, y Carlos los comercios 3
y 4. Entonces, en todas mis pruebas, el comercio ajeno a Ana es el **3** y la
transaccion ajena es la **4**. Cambia el numero, no la idea.

---

## JUEVES — Guia N.o 13

### Laboratorio 1 — Experimento: la puerta sin cerradura

Este experimento lo corri de verdad contra el proyecto de la Semana 13, tal como
quedo entregado, sin ningun token y sin ninguna cookie.

| Peticion | ¿Que respondio? | ¿Deberia permitirse sin identificarse? |
|---|---|---|
| `GET /api/comercios` | **200** con los **cuatro** comercios: los 2 de Ana y los 2 de Carlos | No. Un comerciante no tiene por que ver la lista de los demas |
| `GET /api/transacciones` | **200** con las **cuatro** transacciones, con nombre de cliente y monto de cada una | No. Son los cobros de dos negocios distintos mezclados en una sola lista |
| `POST /api/transacciones` (valida) | **201 Created**. Cree una transaccion de $99.99 a nombre de "Intruso Anonimo" **en el comercio 3, que es de Carlos** | No. Es un cobro falso, registrado en el negocio de otra persona, por alguien que nunca dijo quien era |
| `DELETE /api/transacciones/{id}` | **204 No Content**. Esta fila la verifique la semana pasada (peticion 9 de aquella coleccion); hoy no la repeti sobre esa base para no borrarle datos a la entrega anterior | No. Borrar el registro de un cobro no deberia poder hacerlo nadie sin identidad, y menos sobre un negocio ajeno |

Lo que mas me movio el piso fue el 201. Las dos primeras filas son una fuga de
informacion, que ya es grave, pero la tercera es peor: no estoy leyendo datos de
otro, estoy **escribiendo** en su negocio. Y lo hice en un segundo, con una sola
peticion, desde una terminal, sin cuenta y sin dejar rastro de quien fue.

**Por que el login web de la Semana 12 no protegia nada de esto.** Porque ese
login protege rutas de `web.php` con el middleware `auth`, y ese middleware
pregunta por la sesion. La sesion vive en una cookie que el navegador reenvia
sola en cada clic. Las rutas de `api.php` no pasan por ese middleware, no leen
cookies y no tienen sesion, asi que estar logueado en el navegador no cambia una
coma de lo que responde la API.

**Pregunta de analisis: si la API no usa sesion, ¿que tendria que enviar el
cliente en CADA peticion para que el servidor sepa quien es? ¿Por que no conviene
enviar el correo y la contraseña cada vez?**

Como el servidor no recuerda nada entre una peticion y la siguiente, cada
peticion tiene que llegar identificada por su cuenta, y eso significa mandar una
credencial en un encabezado cada vez, no una sola vez al entrar. Lo que no
conviene es que esa credencial sea el correo y la contraseña, y hay cuatro
razones. La primera es la exposicion: la contraseña viajaria decenas de veces al
dia en vez de una, y cada viaje es una oportunidad de que quede registrada en un
log, en un proxy o en la captura de alguien. La segunda es que la aplicacion
tendria que **guardar la contraseña** en el telefono o en el disco para poder
reenviarla, y una contraseña guardada en el cliente ya no es un secreto. La
tercera es que no se puede revocar en partes: si se filtra, hay que cambiarla, y
cambiarla bota todas las sesiones de todos los dispositivos de esa persona al
mismo tiempo. Y la cuarta es el costo, que no es menor: verificar un hash bcrypt
esta diseñado para ser lento a proposito, asi que comparar la contraseña en cada
peticion seria pagar ese costo cientos de veces por minuto. El token resuelve las
cuatro: es un secreto distinto de la contraseña, se puede apagar uno solo, se
puede hacer caducar y se verifica con un hash rapido.

---

### Laboratorio 2 — Preparar Sanctum y el modelo User

Sanctum ya estaba instalado desde la Semana 13, cuando corri `php artisan
install:api`, asi que no hubo que instalar nada.

```
$ composer show laravel/sanctum
name     : laravel/sanctum
versions : * v4.3.3

$ php artisan migrate:status
2026_10_27_222824_create_personal_access_tokens_table ...... Ran
```

Verificacion:

- [x] `composer show laravel/sanctum` muestra el paquete.
- [x] La tabla `personal_access_tokens` existe y `migrate:status` la marca como Ran.
- [x] `User.php` tiene `use HasApiTokens`.

Lo que agrega el trait, y que uso en los tres metodos del controlador, es
`createToken()` para emitir, `tokens()` para listar y `currentAccessToken()` para
saber con cual token vino firmada la peticion actual.

---

### Laboratorios 3 y 4 — El login y las rutas protegidas

El login quedo en `app/Http/Controllers/Api/AuthApiController.php` y las rutas en
`routes/api.php`, con `/login` afuera del grupo y todo lo demas adentro de
`auth:sanctum`.

Verificacion:

- [x] `POST /api/login` con los datos de Ana responde **201** con un token.
- [x] `POST /api/login` con clave incorrecta responde **422** "Las credenciales no coinciden."
- [x] `GET /api/yo` con el token responde **200** con id, name y email de Ana.
- [x] Mire la tabla `personal_access_tokens` desde tinker y la columna `token`
      guarda un hash, no el texto que recibi.

Eso ultimo lo comprobe asi, y es lo que mas me gusto del dia:

```
>>> substr(User::find(1)->tokens()->first()->token, 0, 25)
= "e38f31ede2b6365459cfef796..."
```

El texto que me entrego el login empieza con `12|` y sigue con otra cosa
completamente distinta. O sea que el token se guarda igual que una contraseña:
solo su hash. Ni yo, con acceso a la base de datos, puedo recuperar el token de
alguien; lo unico que puedo hacer es borrarlo.

---

### Laboratorio 5 — Experimento: ¿que pasa sin token?

| Variante | Codigo obtenido | Forma de la respuesta |
|---|---|---|
| Con `Accept` y con el token | **200** | JSON con los comercios de Ana dentro de `data` |
| Con `Accept` y SIN token | **401** | JSON: `{ "message": "Unauthenticated." }` |
| SIN `Accept` y SIN token | **302** | HTML, con `Location: http://127.0.0.1:8000/login`. Ni un dato del error |

La tercera fila es la que vale la pena entender. El servidor no se equivoco: hizo
exactamente lo que se le pidio. Sin el encabezado `Accept`, Laravel supone que del
otro lado hay un navegador con una persona mirando, y lo util para una persona es
mandarla a la pantalla de login. Para un programa esa respuesta es inservible,
porque un 302 no significa "fallaste", asi que un cliente mal escrito lo tomaria
por exito, y si sigue la redireccion lo que recibe es una pagina HTML de la que
tendria que sacar el error leyendo etiquetas.

Por eso el `Accept: application/json` no es un detalle de configuracion: es la
forma en que el cliente avisa que del otro lado hay una maquina.

Y los tres codigos que aparecen esta semana responden tres preguntas distintas:

| Codigo | Pregunta que responde | Cuando sale en mi API |
|---|---|---|
| 401 | ¿Se quien eres? → No | Sin token, token mal escrito, vencido o revocado |
| 403 | ¿Puedes hacer esto? → No | No lo uso, y es a proposito (lo explico en el Lab 7) |
| 404 | ¿Existe para ti? → No | Ana pide el comercio 3, o una transaccion de Carlos |

---

### Laboratorio 6 — Cada usuario ve solo sus comercios

**Antes de cambiar nada**, con el token de Ana, `GET /api/comercios` devolvia
**los cuatro comercios, incluidos los de Carlos**. El token ya servia para saber
quien era Ana, pero la consulta seguia arrancando en `Comercio::all()`, asi que
la identidad estaba y no se usaba para nada.

Eso es lo que distingue las dos preguntas del dia: **autenticacion** es "¿quien
eres?" y la resolvio el token; **autorizacion** es "¿que podes ver?" y hay que
escribirla aparte. Tener lo primero no da lo segundo.

Despues del cambio:

- [x] Con el token de Ana, `GET /api/comercios` devuelve solo Cafe Amanecer y Pupuseria Doña Marta.
- [x] Con el token de Ana, `GET /api/comercios/3` (de Carlos) responde **404**.
- [x] Con el token de Carlos, `GET /api/comercios` devuelve solo Ferreteria El Tornillo y Libreria La Pagina.

---

### Laboratorio 7 — Las transacciones heredan la proteccion

Una transaccion no tiene columna `user_id`: es de quien sea su comercio. Asi que
el filtro del listado viaja por la relacion (`user -> comercios ->
transacciones`) y la verificacion de las otras tres acciones vive en un metodo
privado, `autorizar()`, que llamo como primera linea de `show()`, `update()` y
`destroy()`.

- [x] Con el token de Ana, `GET /api/transacciones` devuelve solo transacciones de sus comercios.
- [x] Con el token de Ana, GET, PUT o DELETE sobre una transaccion de Carlos responde **404**.
- [x] Con el token de Ana, `POST /api/transacciones` con `comercio_id` 3 responde **422**.

Lo mejor de este laboratorio es que el `store()` no necesito ni una linea nueva.
La regla que dice que el comercio tiene que ser del usuario estaba escrita desde
la Semana 12, dentro de `GuardarTransaccionRequest`, en el `exists` con
`where('user_id')`. Lo que faltaba no era la regla sino el usuario: por la API,
`$this->user()` era null, y la semana pasada tuve que partir la regla en dos con
un `if` para que no reventara. Esta semana el `if` se fue del archivo, porque
dentro de `auth:sanctum` siempre hay usuario.

Tambien hay que decir que hoy no se cierra todo. Por la API, el dueño de una
transaccion Completada todavia la puede borrar, cosa que el CRUD web no permite.
No es un olvido de identidad, es una regla de negocio que quedo pendiente y la
deje anotada en el controlador: una pasarela real no borra un cobro cerrado, lo
anula.

**Un extra que no pedia la guia.** Al probar los 404 me encontre con que, con
`APP_DEBUG=true`, el cuerpo de la respuesta traia cientos de lineas con el nombre
de la excepcion, la ruta absoluta de los archivos en mi disco y toda la pila de
llamadas. El codigo de estado era correcto, pero la respuesta ya no cumplia el
contrato de la API (un cliente que lea `message` recibia una cadena vacia) y de
paso publicaba un mapa del servidor. Agregue en `bootstrap/app.php` un
renderizador que, solo para rutas `api/*`, devuelve el 404 como
`{ "message": "No encontramos ese recurso en tu cuenta." }`. La parte web sigue
mostrando el trace completo, que ahi si lo quiero mientras programo.

---

### Laboratorio 8 — Revocar y expirar

`logout()` borra con `currentAccessToken()->delete()`, o sea **solo** el token con
el que vino firmada esa peticion, y en `config/sanctum.php` deje
`'expiration' => 60 * 24`.

Ciclo completo, probado en ese orden:

1. Login de Ana → **201** y guardo el token.
2. `GET /api/yo` con ese token → **200**.
3. `POST /api/logout` con ese token → **204**.
4. `GET /api/yo` con el **mismo** token → **401**.

El paso 4 es la prueba de que cerrar sesion en una API es otra cosa que en la
web. En la web se destruye la sesion del servidor y se bota la cookie. Aca no hay
sesion: cerrar sesion es borrar la fila del token, y el mismo texto que servia
hace un segundo deja de servir en el instante en que esa fila desaparece. Si solo
lo borrara del lado del cliente no estaria cerrando sesion, estaria olvidandolo:
la llave seguiria abriendo la puerta para quien la tuviera copiada.

**Pregunta de analisis: ¿por que conviene dar a cada token un nombre de
dispositivo? Pensa que harias si a Ana le roban el telefono pero no la laptop.**

Porque sin nombre, todos los tokens de Ana son numeros iguales y la unica
revocacion posible es la bruta: `tokens()->delete()`, que la saca de todas partes
de un solo golpe, incluida la laptop desde la que esta trabajando en ese momento.
Con nombre, la fila deja de ser anonima y se puede decidir cual apagar: Ana entra
desde la laptop, abre su pantalla de sesiones, ve `app-movil-ana` con su ultimo
uso de hace diez minutos y la revoca sola, mientras `laptop-ana` sigue adentro y
ella ni se entera de que algo se interrumpio. Y el nombre sirve para una segunda
cosa que no es revocar sino **darse cuenta**: si en esa lista aparece un
dispositivo que Ana no reconoce, o uno cuyo ultimo uso es de un momento en que
ella no estaba trabajando, eso es la primera señal de que alguien mas tiene una
llave. Sin nombres, ese aviso no existe.

---

### Verificacion profesional completa — la matriz de 10

Corrida con dos tokens, uno de Ana y otro de Carlos, cada uno en su variable.

| # | Token | Peticion | Esperado | Obtenido |
|---|---|---|---|---|
| 1 | — | `POST /api/login` (Ana, datos validos) | 201 + token | **201** + token |
| 2 | — | `POST /api/login` (clave incorrecta) | 422 | **422** "Las credenciales no coinciden." |
| 3 | Ninguno | `GET /api/yo` | 401 | **401** `{"message":"Unauthenticated."}` |
| 4 | Ana | `GET /api/yo` | 200 · datos de Ana | **200** `{"id":1,"name":"Ana López","email":"ana@taskboard.test"}` |
| 5 | Ana | `GET /api/comercios` | 200 · solo los de Ana | **200** · comercios 1 y 2 |
| 6 | Ana | `GET /api/comercios/3` | 404 (es de Carlos) | **404** |
| 7 | Ana | `POST /api/transacciones` con `comercio_id` 3 | 422 | **422** "Ese comercio no esta disponible en tu cuenta." |
| 8 | Carlos | `GET /api/transacciones` | 200 · solo las de sus comercios | **200** · solo la transaccion 4 |
| 9 | Ana | `POST /api/logout` | 204 | **204** sin cuerpo |
| 10 | Ana (el mismo) | `GET /api/yo` | 401 (revocado) | **401** |

---

### Reto del jueves — ¿Donde tengo sesion abierta?

Lo implemente como `GET /api/tokens` (metodo `tokens()` del `AuthApiController`)
y el extra como `DELETE /api/tokens/{id}` (metodo `revocarToken()`).

Respuesta real, con dos sesiones abiertas de Ana:

```json
{
  "data": [
    { "id": 5, "dispositivo": "app-movil-ana", "ultimo_uso": null,
      "creado": "2026-11-02T18:00:41+00:00", "es_el_actual": false },
    { "id": 4, "dispositivo": "laptop-ana", "ultimo_uso": "2026-11-02T18:00:41+00:00",
      "creado": "2026-11-02T18:00:41+00:00", "es_el_actual": true }
  ]
}
```

Criterios de aceptacion:

1. [x] Sin token responde 401; con token responde 200.
2. [x] Cada elemento muestra id, nombre del dispositivo, fecha de creacion y ultimo uso.
3. [x] **Nunca** expone la columna `token` ni datos de otros usuarios.
4. [x] Con dos logins de dispositivos distintos aparecen dos elementos, y despues del logout de uno queda uno.

Agregue ademas `es_el_actual`, que es el "este dispositivo" de las aplicaciones
reales: sale de comparar el id de cada token con el de `currentAccessToken()`.

Sobre el punto 3 vale la pena detenerse. Publicar el hash no entregaria la llave,
porque un hash no se puede invertir, pero si entregaria el material para
intentar adivinarla sin limite y **fuera de la vista del servidor**, o sea sin
que ningun throttle ni ningun log se entere. Una pantalla de dispositivos
conectados no necesita el secreto para cumplir su funcion, asi que el `get()`
lleva la lista de columnas escrita a mano y esa columna no esta.

**¿Que debes verificar antes de borrar el token?** Que el token sea del usuario
que pide el borrado. Sin esa verificacion, cualquiera con una cuenta en el
sistema podria ir probando numeros de id y cerrarle la sesion a los demas, que es
una forma barata de dejar a otro afuera de su propia cuenta. En mi
implementacion esa verificacion no es un `if` aparte: la consulta arranca en
`$request->user()->tokens()`, asi que un id ajeno simplemente no esta en el
conjunto y `findOrFail()` levanta un 404. Me gusta mas asi que con un `if`,
porque no hay forma de olvidarse de escribirlo, y porque un 404 tampoco confirma
que ese id exista en otra cuenta. Lo probe: Ana pidiendo borrar un token de
Carlos recibe 404 y el token de Carlos sigue funcionando.

---

### Bitacora del jueves

**¿Que diferencia hay entre proteger la web con sesion (Semana 12) y proteger la
API con token (hoy)? ¿Por que una app movil no puede usar la sesion del
navegador?**

La diferencia de fondo es quien se acuerda de quien eres. Con sesion, el que se
acuerda es el servidor: guarda un registro con el id del usuario y le manda al
navegador una cookie con la referencia, y de ahi en adelante el navegador la
reenvia sola en cada clic sin que nadie se lo pida. Con token, el servidor no se
acuerda de nada entre una peticion y la siguiente: el que guarda la credencial es
el cliente, y es el cliente el que tiene que mandarla a mano en un encabezado
cada vez. Por eso cerrar sesion tambien es otra cosa en cada caso: en la web se
destruye el registro del servidor, y en la API se borra la fila del token, porque
no hay sesion que destruir.

Una app movil no puede usar la sesion del navegador porque la sesion no es del
usuario ni del sistema: es del navegador. La cookie vive dentro de ese programa,
atada a su dominio, y ni otro navegador en la misma computadora la ve, mucho
menos una aplicacion instalada en un telefono. Pero el problema no es solo donde
esta guardada la cookie: es que la app no tiene nada que reenvie credenciales
sola, no tiene formulario al cual volver cuando el servidor responde una
redireccion, y no tiene a nadie que lea un mensaje de error en pantalla si el
servidor contesta HTML. El token existe justamente para eso, para que la
identidad no dependa de un navegador, y la prueba de que funciona es que esta
semana la misma API la consumieron tres clientes distintos sin cambiarle una
linea: Postman, un script de PHP en tinker y una pagina con `fetch()`.

---

## VIERNES — Guia N.o 14

### Laboratorio 1 — Diagnostico: ¿que responderia la API?

Escribi la prediccion antes de mandar cada peticion.

| # | Situacion | Prediccion | Real |
|---|---|---|---|
| 1 | `GET /api/comercios` sin token, con `Accept` | 401 | **401** |
| 2 | `GET /api/comercios` sin token y SIN `Accept` | 302 a la pagina de login | **302**, con `Location: /login` y cuerpo HTML |
| 3 | Ana pide `GET /api/comercios/3` (de Carlos) | 404 | **404** |
| 4 | Ana crea una transaccion con `comercio_id` 3 | 422 | **422**, con el error en `errors.comercio_id` |
| 5 | Ana usa su token despues de `POST /api/logout` | 401 | **401** |

Las cinco coincidieron, y creo que coincidieron porque los cuatro codigos ya
tienen un significado claro para mi: 401 es "no se quien sos", 404 es "no existe
para vos", 422 es "los datos que mandaste no sirven" y una redireccion con HTML
es "me olvide el `Accept`". Donde si me equivoque fue mas adelante, en el error D
del Laboratorio 5, y justamente porque ahi el codigo de estado no dice nada.

---

### Laboratorio 2 — Una coleccion con dos identidades

**Herramienta elegida: Postman**, igual que la semana pasada, con la coleccion
guardada en el repositorio como `TaskBoard-API-Semana14.postman_collection.json`.
Tambien deje actualizado `pruebas-api.http` para la extension REST Client de VS
Code, para que las pruebas se puedan repetir sin instalar nada aparte.

La coleccion quedo organizada asi:

```
TaskBoard API - Semana 14 (Sanctum)
├── 0. Publicas
│   ├── Login Ana                        -> guarda {{tokenAna}}
│   ├── Login Carlos                     -> guarda {{tokenCarlos}}
│   ├── Login con clave incorrecta       (espera 422)
│   └── Login Ana desde un segundo dispositivo -> guarda {{tokenMovilId}}
├── 1. Ana      (Auth de carpeta: Bearer {{tokenAna}})
│   └── 13 peticiones, de 1.1 Yo a 1.13 Yo con el token ya revocado
├── 2. Carlos   (Auth de carpeta: Bearer {{tokenCarlos}})
│   └── 2.1 Yo · 2.2 Mis comercios · 2.3 Borrar una transaccion de Ana (404)
└── 3. Sin token  (Auth: No Auth)
    └── 3.1 con Accept (401) · 3.2 sin Accept (no es JSON)
```

El token no se pega a mano en ninguna peticion. Cada login guarda el suyo en una
variable de coleccion desde la pestaña Scripts → Post-response:

```js
pm.collectionVariables.set('tokenAna', pm.response.json().token);
```

y la carpeta entera lleva Authorization → Bearer Token → `{{tokenAna}}`, con las
peticiones de adentro en modo heredar. En Bruno el equivalente seria
`bru.setVar("tokenAna", res.getBody().token)` en Script → Post Response, y en
REST Client es nombrar el login con `# @name loginAna` y despues
`@tokenAna = {{loginAna.response.body.token}}`, que es lo que hace mi archivo
`.http`.

Un detalle que me importa: las variables `tokenAna` y `tokenCarlos` quedan
**vacias** en el archivo que subo al repositorio, y las llenan los scripts cuando
la coleccion corre. Un archivo de pruebas con un token real adentro es una llave
publicada, y el cierre de la guia lo dice con todas sus letras.

Verificacion:

- [x] Ejecute Login Ana y Login Carlos y ambos respondieron 201.
- [x] `GET /api/yo` en la carpeta "1. Ana" muestra a Ana, y en "2. Carlos" muestra
      a Carlos, sin pegar ningun token a mano.

---

### Laboratorio 3 — Pruebas automaticas

Cada peticion de la coleccion lleva pruebas en Scripts → Post-response, y no solo
del codigo de estado: tambien de la forma de la respuesta y, sobre todo, de que
no aparezcan datos ajenos. En total quedaron **61 comprobaciones**.

Las que mas me interesan son estas tres, porque son las que detectan fugas que un
codigo de estado nunca delataria:

```js
// 1.2 Mis comercios
pm.test('Ana ve exactamente sus 2 comercios', () => pm.expect(d.length).to.eql(2));
pm.test('no expone user_id', () => pm.expect(d[0]).to.not.have.property('user_id'));

// 1.10 Mis sesiones abiertas
pm.test('NUNCA expone la columna token (el hash)',
        () => pm.expect(d[0]).to.not.have.property('token'));
```

Verificacion:

- [x] Al ejecutar la coleccion completa, todas las pruebas quedan en verde.

**Un aviso sobre el throttle.** La coleccion hace 4 logins, y `/login` tiene
`throttle:5,1`. Correr la coleccion completa dos veces dentro del mismo minuto
devuelve **429** en los ultimos logins. No es un error: es la defensa
funcionando, y el limite cuenta todos los intentos, no solo los fallidos. Lo
comprobe a proposito mandando siete logins seguidos y los sobrantes respondieron
429.

---

### Laboratorio 4 — La matriz de pruebas

| # | Identidad | Peticion | Esperado | Obtenido |
|---|---|---|---|---|
| 1 | — | Login Ana y Login Carlos | 201 + token (x2) | **201** y **201** |
| 2 | — | Login con clave incorrecta | 422 | **422** |
| 3 | Ninguna | `GET /api/comercios` | 401 | **401** |
| 4 | Ana | `GET /api/yo` | 200 · Ana Lopez | **200** · Ana López |
| 5 | Ana | `GET /api/comercios` | 200 · sus comercios | **200** · comercios 1 y 2 |
| 6 | Carlos | `GET /api/comercios` | 200 · sus comercios | **200** · comercios 3 y 4 |
| 7 | Ana | `GET /api/comercios/3` | 404 | **404** |
| 8 | Ana | `POST /api/transacciones` con `comercio_id` 3 | 422 | **422** |
| 9 | Carlos | DELETE una transaccion de Ana (`/transacciones/1`) | 404 | **404**, y la transaccion sigue ahi |
| 10 | Ana | `POST /api/logout` y luego `GET /api/yo` | 204 y luego 401 | **204** y luego **401** |

La fila 9 es la que mas me gusta de toda la semana, porque un solo codigo dice
dos cosas buenas al mismo tiempo: que no se borro nada, y que la respuesta ni
siquiera le confirma a Carlos que esa transaccion exista.

---

### Laboratorio 5 — Romper a proposito y depurar

Provoque los cuatro errores uno por uno, con el metodo de 5 pasos, y repare cada
uno antes de pasar al siguiente.

| # | Codigo obtenido | Que decia el cuerpo o el log | Correccion |
|---|---|---|---|
| A | **401** | `{"message":"Unauthenticated."}`. Nada en el log: para Laravel no hubo ningun error, simplemente no venia nadie identificado | Volver a poner el esquema: `Authorization: Bearer 12|aB9x...`, con la palabra, un espacio y el token completo |
| B | **302** | Cuerpo HTML y `Location: http://127.0.0.1:8000/login`. Tampoco hay nada en el log | Devolver el encabezado `Accept: application/json` a la peticion |
| C | **500** | `Call to undefined method App\Models\User::createToken()`, excepcion `BadMethodCallException`. En el log, la misma linea con la pila: `ForwardsCalls.php:67` y `Model::throwBadMethodCallException('createToken')` | Devolver `use HasApiTokens` al modelo `User` (y su `use Laravel\Sanctum\HasApiTokens;` arriba) |
| D | **200** | Nada. Ni error, ni excepcion, ni una linea en el log. La respuesta trae **4 comercios** en vez de 2 | Volver a `$request->user()->comercios()` en lugar de `Comercio::query()` |

Dos cosas que aprendi de la tabla. La primera es que **el log no sirve para
todo**: solo el error C dejo rastro ahi, porque solo el C fue una excepcion. Un
401 y un 302 son respuestas normales del framework, no fallas, asi que buscarlos
en `laravel.log` es perder el tiempo; esos se leen en el codigo de estado y en el
cuerpo. La segunda es que el error C fue el mas engañoso de los tres visibles: el
mensaje apunta al controlador, que es donde revienta la llamada, pero el archivo
que estaba mal era `User.php`, que ni aparece en la primera linea del error.

**Pregunta de analisis: el error D no produjo ningun codigo de error, todo
respondio 200. ¿Como lo detectaste? ¿Que prueba automatica del Laboratorio 3 lo
habria marcado en rojo?**

Lo detecte contando. La respuesta era un 200 perfectamente valido, con la
estructura correcta y los campos correctos, y mirandola rapido parecia bien; lo
unico que estaba mal era **cuantos** elementos traia, cuatro en vez de dos, y eso
no se nota a ojo si uno no sabe de memoria que Ana tiene dos comercios. La prueba
que lo habria marcado en rojo es
`pm.test('Ana ve exactamente sus 2 comercios', () => pm.expect(d.length).to.eql(2))`,
y mas todavia la que compara los ids, `expect(d.map(c => c.id)).to.eql([1, 2])`,
porque esa falla aunque el total coincida por casualidad.

Para mi esa es la leccion del dia entero. Los otros tres errores gritan: cambian
el codigo de estado y se ven solos. El D es silencioso, responde 200 y de todos
los cuatro es el unico que **filtra datos de otra persona**. Si la unica prueba
que tuviera fuera "responde 200", ese error pasaria las pruebas en verde y la
fuga se iria a produccion. Por eso una prueba automatica que solo revisa el
codigo de estado da una falsa sensacion de seguridad: lo que hay que afirmar es
tambien **cuantos** datos vienen y **de quien** son.

- [x] Repare los cuatro errores y volvi a correr la coleccion completa: todo en verde otra vez.

---

### Laboratorio 6 — Inspeccionar los tokens en tinker

```
>>> User::find(1)->tokens()->get(['id', 'name', 'last_used_at'])
  id=11  name=error-d    last_used_at=2026-11-02 18:07:54
  id=12  name=reparado   last_used_at=2026-11-02 18:08:03

>>> use Laravel\Sanctum\PersonalAccessToken;
>>> PersonalAccessToken::findToken('99|estetokennoexiste')
= NULL

>>> Comercio::find(3)->user_id
= 2
```

| Linea | Que responde |
|---|---|
| `tokens()->get(...)` | Que tokens tiene Ana, con que nombre y cuando se usaron por ultima vez |
| `findToken('...')` | Si un token concreto existe. `null` significa revocado, vencido o mal copiado |
| `Comercio::find(3)->user_id` | De quien es el comercio 3: del usuario 2, o sea Carlos |

- [x] Vi los tokens de Ana con su nombre de dispositivo.
- [x] `findToken()` con un token revocado devuelve `null`.

Esta tercera linea es la que mas me sirvio como herramienta de depuracion, porque
responde sin pasar por la API: cuando algo da 404 y no se si es porque la fila no
existe o porque es ajena, `Comercio::find(3)->user_id` lo dice en un segundo.

---

### Laboratorio 7 — Consumir la API desde PHP

Corrido en `php artisan tinker`, haciendo de cuenta que soy una tienda en linea
que solo conoce el contrato de la API:

```
login status: 201
consulta status: 200
successful(): true
data: [{"id":1,"comercio_id":1,"cliente":"María López","monto":45,
        "estado":"Completada","fecha":"2026-11-02T17:52:43+00:00"}]
```

| Metodo | Que hace |
|---|---|
| `acceptJson()` | Agrega `Accept: application/json` |
| `withToken($token)` | Agrega `Authorization: Bearer $token` |
| `post()` / `get()` | Envia la peticion; `get()` acepta un arreglo de parametros de consulta |
| `status()` / `successful()` | El codigo de estado / true si es 2xx |
| `json('clave')` | Lee un campo de la respuesta JSON |

Y la nota de la guia sobre por que esto va en tinker y no en una ruta del
proyecto la entendi recien al leerla: `php artisan serve` atiende **una** peticion
a la vez, asi que una ruta del proyecto que llamara a la API del mismo proyecto
dejaria al servidor esperandose a si mismo. tinker corre en otro proceso, asi que
si puede.

**Ejercicio: repetir la consulta con un token inventado. ¿Que devuelve
`$r->status()`? Escribi el `if` que deberia tener la tienda.**

```
con un token inventado -> status: 401
cuerpo: {"message":"Unauthenticated."}
```

El `if` que deberia tener la tienda es este, y lo importante es el orden: revisar
el codigo **antes** de tocar los datos.

```php
$r = Http::withToken($token)->acceptJson()->get("$base/transacciones");

if ($r->status() === 401) {
    // El token murio: vencido, revocado o mal guardado. No es un error
    // del que haya que avisarle a nadie todavia: lo que toca es pedir
    // uno nuevo y reintentar UNA vez, para no entrar en un ciclo.
    $token = $this->volverAIniciarSesion();

    $r = Http::withToken($token)->acceptJson()->get("$base/transacciones");
}

if ($r->status() === 429) {
    // Pase el limite de intentos: esperar y reintentar despues.
    return $this->reintentarMasTarde();
}

if (! $r->successful()) {
    // Cualquier otra cosa (500, 503, la API caida) no la puedo arreglar
    // yo: registro el problema y muestro los ultimos datos que tenga.
    Log::error('TaskBoard respondio ' . $r->status());

    return $this->datosEnCache();
}

$cobros = $r->json('data');   // recien aca toco los datos
```

Sin ese primer `if`, `$r->json('data')` devolveria `null` en un 401 y el error
reventaria mucho mas adelante, en el lugar equivocado y con un mensaje que no
tiene nada que ver con la causa: un "intentando recorrer null" en vez de "tu
sesion vencio".

---

### Laboratorio 8 — Un cliente web con `fetch()`

Creado en `public/cliente.html`.

- [x] Con Ana veo 2 comercios; con Carlos veo 2 (en mi seeder los dos tienen dos).
- [x] Con una contraseña incorrecta aparece "Credenciales incorrectas (422)".
- [x] En F12 → Red se ve la peticion con el encabezado `Authorization: Bearer ...`.

| Detalle del codigo | Por que |
|---|---|
| `base = '/api'` sin dominio | La pagina y la API salen del mismo servidor, asi que no hay problema de CORS |
| El token en una variable | Se pierde al recargar; guardarlo en `localStorage` lo deja legible para cualquier script que corra en la pagina |
| `textContent`, no `innerHTML` | Si un nombre de comercio trajera HTML, no se ejecutaria |
| `if (r.status === 401)` | Un buen cliente reacciona al codigo antes de usar los datos |

---

### Reto del viernes — Ampliar el cliente web

Criterios de aceptacion:

1. [x] Al lado de cada comercio aparece la cantidad de transacciones Completadas.
2. [x] Un boton **Salir** llama a `POST /api/logout`, borra el token de la variable y vuelve a mostrar el formulario.
3. [x] Si cualquier peticion responde 401, la pagina muestra el formulario de login otra vez.
4. [x] Ningun dato se inserta con `innerHTML`.

El conteo sale de `GET /api/transacciones?estado=Completada` agrupado por
`comercio_id` con un `Map`. Para que eso fuera posible tuve que **agregar
`comercio_id` al `TransaccionResource`**, que la semana pasada no salia. Lo pense
dos veces, porque el criterio de la Semana 13 fue sacar los ids internos, pero el
que saque fue `user_id` y por un motivo concreto: decia a que persona pertenecia
cada fila en una API donde cualquiera veia todas las filas. Con `comercio_id` no
pasa eso, porque ahora la API solo devuelve transacciones del dueño del token,
asi que el unico comercio que puede aparecer ahi ya es suyo.

Las dos peticiones salen en paralelo con `Promise.all`, y el 401 se maneja en un
solo lugar: una funcion `pedir()` que arma el encabezado `Authorization` y, si la
respuesta es 401, devuelve al formulario y corta. Asi ninguna de las tres
peticiones protegidas puede olvidarse de revisarlo.

**Despues de pulsar Salir, ¿como compruebo que el token de "cliente-web"
realmente se revoco?**

De tres formas, y conviene saber las tres porque prueban cosas distintas. La mas
directa es desde tinker, preguntando por la fila:
`User::find(1)->tokens()->pluck('name')` ya no incluye `cliente-web`, o
`PersonalAccessToken::findToken('<el token>')` devuelve `null`; eso prueba que el
registro **se borro de la base**. La segunda es desde Postman, pegando ese mismo
token a mano en una peticion a `GET /api/yo`: si responde **401**, prueba que el
token **ya no abre la puerta**, que es lo que de verdad importa. Y la tercera es
desde la propia pagina, con `GET /api/tokens` antes y despues de salir: la sesion
`cliente-web` desaparece de la lista. La segunda es la que no me puedo saltar,
porque borrar el token del navegador y revocarlo en el servidor se ven igual
desde la pantalla, y solo una de las dos apaga la llave.

---

### Revision de seguridad y cierre

| Revision | Como lo comprobe | ✓ |
|---|---|---|
| Todas las rutas, salvo el login, exigen token | `php artisan route:list --path=api`: 12 rutas, y las 11 protegidas muestran `auth:sanctum` | ✓ |
| El login tiene limite de intentos | Mande 7 logins seguidos y los sobrantes respondieron **429** | ✓ |
| Nadie ve datos ajenos | La matriz del Lab 4 completa en verde, 61 comprobaciones | ✓ |
| El token caduca | `config/sanctum.php` con `'expiration' => 60 * 24` | ✓ |
| No hay tokens reales en el repositorio | Busque `Bearer 1|` y `Bearer ` en los archivos de prueba: la coleccion solo trae `{{tokenAna}}` y `{{tokenCarlos}}`, y las variables van vacias | ✓ |
| `APP_DEBUG=false` fuera de mi computadora | En mi `.env` local sigue en `true`, que es lo correcto para programar. Ademas, para no depender solo de ese interruptor, el 404 de la API ya devuelve JSON limpio aunque el debug este encendido | ✓ |

Checklist del dia:

- [x] Coleccion con carpetas Publicas, Ana y Carlos; tokens guardados por script.
- [x] Pruebas automaticas en cada peticion; la coleccion completa en verde.
- [x] Diagnostique y repare los cuatro errores del Laboratorio 5.
- [x] Consumi la API desde tinker con `Http::withToken()`.
- [x] `public/cliente.html` funciona con Ana y con Carlos.

---

### Bitacora del viernes

**¿Cual de los cuatro errores del Laboratorio 5 te costo mas diagnosticar y por
que? ¿Que te enseño que la tienda en linea (PHP) y la pagina web (JavaScript)
pudieran usar la misma API sin conocer nada de su codigo interno?**

El que mas me costo fue el **D**, y no estuvo cerca. Los otros tres se delatan
solos: cambian el codigo de estado, y con el metodo de 5 pasos uno llega en dos
minutos. El D respondio 200, con la estructura correcta y los campos correctos, y
lo unico que estaba mal era que traia cuatro comercios en vez de dos. Mirandolo
rapido parecia bien, y ahi esta lo incomodo: de los cuatro errores, el unico
silencioso es tambien el unico que filtra datos de otra persona. Me dejo la
leccion de que una prueba que solo afirma "responde 200" no prueba casi nada, y
que la afirmacion que de verdad protege es cuantos datos vienen y de quien son.

Lo de los dos clientes me dejo algo distinto, y creo que es lo que la materia
viene buscando desde el principio. Ni el script de PHP ni la pagina de
JavaScript saben que existe una tabla llamada `comercios`, ni que hay un modelo
Eloquent, ni con que motor de base de datos corre esto. Lo unico que conocen son
tres direcciones, un token y la forma del JSON, y con eso les alcanzo para
trabajar. Eso significa que lo que importa de verdad no es el codigo que escribi
adentro sino el **contrato** que publique afuera: adentro puedo cambiar el motor
de base de datos, renombrar una columna o reescribir un controlador y los dos
clientes siguen funcionando sin que nadie los toque; pero si le cambio el nombre
a un campo de la respuesta, o le cambio el codigo de estado al login, los rompo a
los dos de una vez aunque el proyecto siga compilando. Integrar sistemas es
sostener esa promesa, y por eso la coleccion de pruebas no es un tramite de la
guia: es lo unico que avisa cuando la promesa se rompe.
