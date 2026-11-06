# Respuestas y bitacora — Semana 15

Guia Practica N.o 15: ¿Como se prueba una API? Estrategias y casos limite (jueves)
Guia Practica N.o 16: Manejo de errores e integridad de los datos (viernes)

Alumno: Hector Alonso Interiano Figueroa
Asignatura: Integracion de Sistemas — CE-ISC019
Ciclo: 02-2026

Las dos guias son una sola entrega, asi que este archivo responde las dos. La
bitacora con los 15 casos y los cinco reportes de error esta en
`Bitacora_Pruebas_Semana15_Interiano.xlsx`, que va en este mismo repositorio.

Datos sembrados por el seeder, que son los que usan todas las pruebas:

| Comercio | Dueño | Transacciones |
|---|---|---|
| 1 Cafe Amanecer | Ana | 1 Completada, 2 Iniciada |
| 2 Pupuseria Doña Marta | Ana | 3 Fallida |
| 3 Ferreteria El Tornillo | Carlos | — |
| 4 Libreria La Pagina | Carlos | 4 Iniciada |

**Dos diferencias con la guia, que cambian numeros pero no ideas.**

1. La guia supone que la columna `monto` es `decimal(10,2)`, con tope
   99 999 999.99. En este proyecto es **`decimal(8,2)`**, asi que el tope real es
   **999 999.99** y esa es la cota que uso en la correccion. El caso CL-07 sigue
   siendo valido igual: 100 000 000 esta muy por encima de los dos topes.
2. La guia nombra el Form Request como `StoreTransaccionRequest`. Aca se llama
   **`GuardarTransaccionRequest`**, porque desde la Semana 10 lo comparten
   `store()` y `update()` de las dos puertas, la web y la API.

---

## JUEVES — Guia N.o 15

### Laboratorio 1 — Analisis del hallazgo CL-07

**1. ¿Que tecnica descubrio este error y por que se eligio el valor 100 000 000?**

Valores limite. La tecnica dice que los errores se juntan en los bordes, y este
caso prueba un borde que la validacion no conocia: el de la columna. Las reglas
del monto cubrian el borde de abajo (`min:0.01`) y ninguno de arriba, asi que
por arriba el unico limite que quedaba era el fisico, el de `decimal`. El valor
100 000 000 no sale al azar: es el primer numero redondo que pasa el tope de
`decimal(10,2)`, que admite hasta 99 999 999.99. Con mi columna, que es
`decimal(8,2)` y llega hasta 999 999.99, el borde esta mas abajo todavia, y lo
comprobe: **1 000 000 tambien responde 500**, con el mismo error.

**2. ¿Por que el mismo codigo responde 500 con MySQL y 201 con SQLite?**

Porque el limite no lo pone el codigo, lo pone el motor, y los dos motores
tratan `decimal(8,2)` de forma distinta. MySQL respeta la precision declarada y
rechaza lo que no entra, asi que lanza el error 1264 "Out of range value" y
Laravel lo convierte en una excepcion, o sea un 500. SQLite, en cambio, casi no
tiene tipos: `decimal(8,2)` le sirve de pista y nada mas, guarda el numero
completo y responde 201 como si todo estuviera bien.

Lo incomodo es que el caso mas peligroso es el que NO falla. Con SQLite no hay
error, no hay log y no hay sintoma: queda un cobro guardado con un valor que la
base de produccion no habria aceptado nunca. Y de ahi sale la leccion de fondo:
**si la unica defensa de un dato esta en la base, la aplicacion se comporta
distinto segun donde corra**, y las pruebas que pasan en la maquina del
desarrollador no dicen nada de lo que va a pasar en el servidor. La validacion,
en cambio, es la misma en los dos lados.

**3. ¿Que severidad le darias?**

**Alta**, segun la tabla de la guia: "la funcion falla o el sistema se cae (por
ejemplo, un 500)". Con MySQL el 500 es literal. Pero la razon de fondo es otra y
pesa mas: un 500 significa "culpa del servidor", y aca la culpa era del dato que
mando el cliente. Un cliente bien escrito que recibe 500 reintenta, porque un
500 suele ser pasajero, y va a reintentar para siempre con el mismo dato
invalido sin que nadie le explique nunca que el problema es suyo. Con SQLite
subiria a **Critica**, porque ahi no hay error: hay un cobro guardado mal.

**4. ¿En que archivo y en que regla deberia estar el arreglo?**

En `app/Http/Requests/GuardarTransaccionRequest.php`, dentro de `rules()`, en la
regla de `monto`, agregando un `max`. La validacion es el lugar correcto porque
es el unico punto donde el limite se puede explicar: devuelve 422, nombra el
campo y dice cual es el maximo. La base de datos tambien lo frena, pero lo unico
que sabe hacer es reventar.

---

### Laboratorio 2 — Los dos casos resueltos

- [x] CL-02 (monto 0) en **verde**: 422 con el error en `monto`.
- [x] CL-07 (monto 100000000) en **rojo**: la API respondio **500** y la prueba
      esperaba 422.
- [x] Entiendo por que las filas CL-02 y CL-07 de la bitacora dicen lo que dicen.

Una prueba en rojo no es un fracaso: es la evidencia de que el error existe. No
la cambie ni la borre, y el viernes se puso en verde sola, sin tocarla.

---

### Laboratorio 3 — Los cinco semiarmados

Prediccion escrita ANTES de enviar, como pide la regla de oro.

| ID | Caso | Mi esperado | Obtenido | Resultado |
|---|---|---|---|---|
| CL-01 | monto 0.01 | 201 | **201** | ✓ Pasa |
| CL-03 | monto -0.01 | 422 · monto | **422** "El monto debe ser mayor a cero." | ✓ Pasa |
| CL-04 | comercio_id 999 | 422 · comercio_id | **422** "Ese comercio no esta disponible en tu cuenta." | ✓ Pasa |
| CL-05 | monto "25,50" | 422 · monto | **422** "El monto debe ser un numero." | ✓ Pasa |
| CL-09 | monto 25.999 | 422 · monto | **201**, y guardo 26.00 | ✗ Error E-02 |

Por que escribi cada esperado:

- **CL-01** es el otro lado del borde de CL-02. Si una regla de minimo esta mal
  escrita puede rechazar los dos, y sin este caso eso no se nota.
- **CL-03** porque un cobro negativo no es un cobro: seria una devolucion, y eso
  tendria que ser otra operacion con su propia regla.
- **CL-04** porque 422 y no 404: el 404 diria que la DIRECCION no existe, y
  `/api/transacciones` si existe. Lo que esta mal es un dato del cuerpo.
- **CL-05** porque el formato lo decide quien muestra el dato, no quien lo
  manda. Si la API aceptara la coma tendria que adivinar si separa decimales o
  miles, y "1,500" seria 1.5 o 1500 segun el pais de quien escribio.
- **CL-09** porque la columna guarda dos decimales y un tercero no se puede
  representar, asi que la API deberia decirlo en vez de inventar un valor.

El aviso de la guia ("uno de estos cinco es un error conocido") cayo en CL-09, y
me alegro de haber escrito el esperado antes: viendo la respuesta primero, un
201 se lee como exito y la fila habria quedado en ✓ Pasa sin que yo mirara el
monto guardado.

---

### Laboratorio 4 — Los ocho casos desde cero

| ID | Caso | Tecnica | Mi esperado | Obtenido | Resultado |
|---|---|---|---|---|---|
| CL-06 | comercio_id "abc" | Caso negativo | 422 · comercio_id, nunca 500 | **422** | ✓ Pasa |
| CL-08 | nombre de 255 caracteres | Valor limite | 201 con el nombre entero | **201**, 255 caracteres | ✓ Pasa |
| CL-10 | nombre de 256 caracteres | Valor limite | 422 · cliente_nombre, mensaje en español | **422** con `validation.max.string` | ✗ Error E-04 |
| CL-11 | nombre con solo espacios | Particion | 422 · cliente_nombre | **422** | ✓ Pasa |
| CL-12 | `"estado": "Completada"` al crear | Contrato | no pude decidirlo | **201**, y lo descarto en silencio | ⚠ → E-03 |
| CL-13 | JSON con una coma al final | Caso negativo | 400 | **422** "faltan los 3 campos" | ✗ Error E-05 |
| CL-14 | JSON sin Content-Type | Caso negativo | no pude decidirlo | **422** "faltan los 3 campos" | ⚠ Contrato ambiguo |
| CL-15 | DELETE dos veces | Idempotencia | 204 y luego 404 | **204** y **404** | ✓ Pasa |

Lo que me dejo cada uno:

**CL-06.** Pasa, y pasa de rebote. La regla `exists` compara el texto "abc"
contra la columna `id` y no encuentra nada, asi que lo rechaza como si fuera un
id inexistente. El resultado es correcto pero el mensaje dice "ese comercio no
esta disponible", cuando el problema real es que "abc" no es un numero. Lo dejo
anotado como algo que funciona por casualidad y no por diseño.

**CL-11.** Tambien pasa, y tambien por algo que no esta en el Form Request: la
regla `required` por si sola aceptaria cinco espacios. Lo que los convierte en
vacio es el middleware `TrimStrings` de Laravel, que recorta toda la entrada
ANTES de validar. Me parecio importante entenderlo: si mañana alguien saca ese
middleware, este caso se cae y el Form Request se ve igual de correcto que hoy.

**CL-12.** Este es el que mas me costo, y no por tecnica sino porque no supe que
escribir en la columna "Esperado". Hay tres respuestas defendibles: aceptar el
estado, ignorarlo o rechazarlo. Que no hubiera una decision tomada es
exactamente lo que la guia llama ⚠ Contrato ambiguo, y el hallazgo es ese. El
viernes lo converti en decision.

**CL-13 y CL-14** son hermanos: el mismo sintoma por dos causas distintas. Los
dos respondian 422 reclamando `comercio_id`, `cliente_nombre` y `monto`, y los
tres campos **si venian en la peticion**. La causa es que Laravel solo convierte
el cuerpo en datos cuando puede: con el JSON mal escrito no puede, y sin el
encabezado `Content-Type` ni lo intenta. En los dos casos `$request->all()`
queda vacio y la validacion hace lo unico que puede hacer con un arreglo vacio.

La respuesta es coherente con lo que la validacion vio; el problema es que lo
que vio no era la peticion que se envio. Para el programador del otro lado eso
es peor que un error: es una pista falsa que lo manda a revisar tres campos que
estan perfectos.

**Casos propios (CL-16 y CL-17).** Agregue dos:

- **CL-16, nombre con emoji.** Un emoji ocupa cuatro bytes en UTF-8 y, si la
  base no estuviera en `utf8mb4`, se guardaria recortado o daria error. Lo probe
  porque es un caso real: la gente escribe nombres con tildes, con ñ y con
  emojis, y un recorte silencioso ahi es un dato corrupto que nadie ve hasta que
  alguien busca al cliente y no lo encuentra. Resultado: 201 y el emoji vuelve
  igual.
- **CL-17, monto como texto `"25.50"`.** La regla `numeric` acepta una cadena
  que contenga un numero, y me parece bien que la acepte: muchos clientes de
  JavaScript mandan los valores de un formulario como texto sin darse cuenta. Lo
  que si tiene que cumplirse es que la RESPUESTA lo devuelva como numero, porque
  ese es el contrato que fijo el Resource en la Semana 13. Resultado: 201 y
  `monto: 25.5` como numero.

---

### Laboratorio 5 — Los reportes de error

Cinco reportes con sus seis partes, en la hoja **Reportes de error** de la
bitacora. Resumen:

| ID | Caso | Titulo | Severidad |
|---|---|---|---|
| E-01 | CL-07 | Un monto sobre el maximo de la columna provoca un error 500 | Alta |
| E-02 | CL-09 | Un monto con tres decimales se acepta y se guarda redondeado | Alta |
| E-03 | CL-12 | El campo estado se descarta en silencio al crear | Media |
| E-04 | CL-10 | El error de nombre demasiado largo llega sin traducir | Baja |
| E-05 | CL-13 | Un JSON mal escrito se reporta como campos faltantes | Media |

Sobre la severidad de **E-02**, que es la que mas discutiria. Segun la tabla
seria Media ("funciona, pero con un resultado incorrecto"), pero el resultado
incorrecto aca es **dinero**: el cliente pidio cobrar 25.999 y la pasarela
registro 26.00, un centavo de mas, con un 201 que le asegura que todo salio
bien. Un centavo por transaccion, repetido miles de veces, es plata real que
nadie cuadro nunca. Le puse Alta, y si el redondeo fuera siempre hacia arriba
defenderia Critica.

- [x] Escribi 5 reportes (el modelo E-01 mas cuatro).
- [x] Cada ✗ Error de la bitacora tiene su E-xx en la columna Error.

---

### Experimento de idempotencia (CL-15)

| Envio | Codigo obtenido | ¿Existe la transaccion despues? |
|---|---|---|
| Primero | **204 No Content** | No |
| Segundo | **404 Not Found** | No |

**1. Los codigos fueron distintos, pero ¿el estado final de la base cambio entre
el primer y el segundo envio?**

No, y esa es toda la idea. Despues del primer DELETE la fila no esta; despues
del segundo tampoco. Los codigos son distintos porque responden preguntas
distintas: el 204 dice "la borre", el 404 dice "no hay nada con ese id". Los dos
son correctos y los dos dejan la base igual. Eso es ser idempotente: repetir la
operacion no cambia el resultado.

Y es util de verdad, no es teoria: si a un cliente se le corta la conexion
despues de mandar el DELETE y no sabe si llego, puede reintentar sin miedo. Lo
peor que le pasa es recibir un 404.

**2. Si una tienda reintenta un POST de cobro porque no recibio respuesta a
tiempo, ¿cuantos cobros quedan registrados? ¿Por que es grave?**

Dos. POST no es idempotente: cada envio crea un recurso nuevo, y el servidor no
tiene forma de saber que el segundo es el mismo cobro del primero y no un
segundo cobro legitimo al mismo cliente por el mismo monto. Para la base los dos
son validos.

Es grave porque el error no se nota del lado que lo causa. La tienda ve una
peticion sin respuesta y asume que fallo; el cliente ve dos cargos en su estado
de cuenta. Nadie en el sistema tiene la informacion completa, y el que reclama
es el unico que vio las dos. Y no se arregla reintentando menos: la red se corta
igual, y una tienda que no reintenta pierde cobros reales.

Por eso las pasarelas de verdad piden una **clave de idempotencia**: el cliente
genera un identificador unico para cada intento de cobro y lo manda en un
encabezado. Si llega dos veces la misma clave, el servidor devuelve el resultado
del primer intento en lugar de cobrar otra vez. La responsabilidad de distinguir
"reintento" de "cobro nuevo" pasa al que si lo sabe, que es el cliente.

---

### Extra — La primera prueba en PHPUnit

El proyecto ya traia `phpunit.xml` (base SQLite en memoria, entorno testing) y
`tests/TestCase.php`, asi que solo hubo que escribir la prueba:

```
php artisan test --filter=CrearTransaccionTest

PASS  Tests\Feature\CrearTransaccionTest
✓ cl02 monto cero responde 422
```

Y el reto del extra: agregue el metodo de CL-07 (monto 100000000, esperado 422)
y el jueves salio en **rojo**, como tenia que salir. El viernes, con la regla
`max`, se puso en verde sin tocar la prueba.

Lo que me quedo claro es en que se diferencian las dos herramientas, porque al
principio me parecian la misma cosa escrita dos veces. Bruno necesita el
servidor encendido y usa la base real con los datos que tenga en ese momento:
sirve para explorar y para mostrarle a alguien que funciona. PHPUnit no necesita
servidor, la base nace limpia en cada metodo y corre en un segundo con un solo
comando: sirve para que un error corregido no vuelva sin que nadie se entere.
Son dos momentos distintos del mismo trabajo.

---

### Bitacora de aprendizaje del jueves

**¿Que caso te sorprendio mas y por que? ¿Por que crees que los errores tienden
a concentrarse en los bordes?**

El que mas me sorprendio fue CL-09, el de los tres decimales, y me sorprendio
justamente porque no parecia un error. Los otros gritan: CL-07 devuelve un 500 y
CL-13 devuelve un mensaje raro. CL-09 devuelve **201 Created**, con la estructura
correcta y todos los campos en su lugar, y si uno no va a mirar la fila guardada
no se entera de nada. Lo que paso ahi es que la base recibio 25.999, no lo pudo
guardar tal cual, y en vez de quejarse lo redondeo a 26.00 y siguio. El cliente
pidio cobrar una cosa, se cobro otra, y la API le contesto que todo salio bien.
De los seis problemas que encontre, es el unico que cambia dinero sin que nadie
lo note.

Sobre los bordes, creo que es porque en el medio de un rango uno no tiene que
pensar: un monto de 25.50 funciona porque esta lejos de todo limite y cualquier
codigo razonable lo procesa bien. En el borde, en cambio, hay que tomar una
decision explicita, y decidir es donde uno se equivoca: ¿el minimo incluye el
0.01 o empieza despues? ¿255 entra o es el primero que sobra? ¿Que pasa con el
tercer decimal? Nadie escribe mal `monto > 0`; lo que se escribe mal es `>=`
cuando era `>`. Y hay una segunda razon, que es la que mas me llamo la atencion
esta semana: los bordes son el lugar donde se tocan dos piezas que nadie puso de
acuerdo. Las reglas de validacion las escribi yo, el tipo de la columna lo
escribio la migracion hace semanas, y en el medio quedo un hueco de
999 999.99 para arriba que ninguna de las dos piezas estaba cuidando. En el
centro del rango las dos coinciden; en el borde se nota que nunca hablaron.

---

## VIERNES — Guia N.o 16

### Linea base antes de tocar codigo

Corrida completa de la coleccion con el codigo del jueves:

**11 casos pasan · 6 fallan** · Casos en rojo: **CL-07, CL-09, CL-10, CL-12,
CL-13 y CL-14**.

Ese numero tiene que bajar a cero al final del dia, y los 11 que ya pasaban
tienen que seguir pasando.

Un detalle de lectura, porque el Runner muestra otro numero: el Runner cuenta
**peticiones**, y la coleccion tiene 25 (los 17 casos mas el login, el LEEME,
los tres pasos de CL-15 contados aparte y las cuatro de limpieza). Asi que ahi
la linea base se ve como **19 en verde y 6 en rojo**. Los 6 en rojo son los
mismos; lo que cambia es sobre cuantos se cuentan.

---

### Laboratorio 1 — Analiza antes de corregir

| Error | Caso | Causa | Correccion que propongo | Archivo |
|---|---|---|---|---|
| E-01 | CL-07 · monto 100000000 | La validacion no tiene tope por arriba; el unico limite es el de la columna `decimal(8,2)`, y ese solo sabe reventar | Regla `max:999999.99` | `GuardarTransaccionRequest` |
| E-02 | CL-09 · monto 25.999 | `numeric` acepta cualquier cantidad de decimales; MySQL redondea al guardar | Regla `decimal:0,2` | `GuardarTransaccionRequest` |
| E-03 | CL-12 · estado al crear | El campo se descarta con `only()` sin avisar | Regla `'estado' => ['prohibited']` | `GuardarTransaccionRequest` |
| E-04 | CL-10 · nombre de 256 | La regla `max:255` existe pero no tiene mensaje escrito, y no hay carpeta `lang/es` | Mensaje `cliente_nombre.max` en `messages()` | `GuardarTransaccionRequest` |
| E-05 | CL-13 · JSON con coma | Sin poder parsear el cuerpo, la validacion ve un arreglo vacio y reclama lo obligatorio | Middleware que revise el cuerpo antes de validar: 400 si el JSON no se lee | `app/Http/Middleware/ValidarCuerpoJson.php` (nuevo) |
| ⚠ | CL-14 · sin Content-Type | Misma causa que E-05, otra razon | Decidir el contrato: **415** Unsupported Media Type, en el mismo middleware | `ValidarCuerpoJson` |

**Pregunta de analisis: para E-01 alguien propone "cambiar la columna a
decimal(15,2) para que quepa". ¿Resuelve el error? ¿Que pasaria con un monto de
un billon?**

No lo resuelve: lo mueve. Con `decimal(15,2)` el monto de 100 000 000 entraria y
el 500 desapareceria para ese caso, pero el borde sigue existiendo, solo que mas
arriba. Un billon (1 000 000 000 000 000) vuelve a pasarse y vuelve el mismo 500
con el mismo mensaje. Habria corrido el problema, no lo habria arreglado, y de
paso habria hecho mas dificil encontrarlo: ahora el valor que lo dispara es tan
grande que nadie lo prueba por casualidad.

Y hay algo peor que el borde que queda. Agrandar la columna responde a la
pregunta equivocada. La pregunta no es "¿cuanto cabe?" sino **"¿cual es un monto
valido para esta pasarela?"**, y esa es una decision de negocio, no de
almacenamiento: un cobro de cien millones de dolares en una pasarela que atiende
pupuserias y cafeterias no es un dato que no quepa, es un dato que esta mal, y
probablemente sea un error de tipeo o un ataque. Con la columna agrandada ese
cobro se guardaria sin que nadie lo mirara.

La diferencia de fondo es quien contesta. Validando, contesta la aplicacion, que
puede decir 422, nombrar el campo y explicar el limite. Dejandoselo a la
columna, contesta el motor de la base de datos, que solo sabe lanzar un error, y
encima contesta distinto segun el motor: lo mismo que con SQLite ni siquiera
falla. **La columna es la ultima red, no la regla.**

---

### Laboratorio 2 — Corregir la validacion

```php
'monto' => [
    'required',
    'numeric',
    'decimal:0,2',     // E-02
    'min:0.01',
    'max:999999.99',   // E-01 — tope de decimal(8,2)
],
'estado' => ['prohibited'],   // E-03
```

Y los mensajes en español, incluido el que faltaba desde la Semana 9:

```php
'cliente_nombre.max' => 'El nombre del cliente no puede pasar de 255 caracteres.',
'monto.decimal'      => 'El monto admite como máximo dos decimales.',
'monto.max'          => 'El monto no puede superar 999,999.99.',
'estado.prohibited'  => 'El estado no se envía al crear: toda transacción nace Iniciada.',
```

`decimal:0,2` y no `decimal:2`: con un solo numero exige EXACTAMENTE dos
decimales, y eso rechazaria 25 y 25.5, que son montos perfectamente validos. Con
`0,2` acepta de cero a dos. Lo deje cubierto con una prueba propia
(`test_la_correccion_de_e02_no_rompe_los_montos_normales`), porque es el error
que la guia avisa que se comete y no quiero depender de acordarme.

El E-05 y el CL-14 se corrigieron con un middleware nuevo,
`app/Http/Middleware/ValidarCuerpoJson.php`, registrado en el grupo `api`. Tiene
que ser un middleware y no una regla de validacion por una razon de orden: para
cuando la validacion entra en escena, el cuerpo ya se intento leer y el daño ya
esta hecho.

- [x] CL-07, CL-09, CL-10 y CL-12 responden 422 con el campo correcto.
- [x] CL-13 responde 400 y CL-14 responde 415.
- [x] CL-01 (0.01) y una transaccion normal (25.50) siguen respondiendo 201.

**¿Y la web?** El formulario de la Semana 9 usa el mismo
`GuardarTransaccionRequest`, asi que recibio las mismas correcciones sin tocarlo.
Revise que el formulario no mande un campo `estado`, porque con la regla
`prohibited` eso lo habria roto: no lo manda, el estado se mueve despues con
`moverEstado()`.

---

### Laboratorio 3 — Regresion

| | Pasan | Fallan |
|---|---|---|
| Linea base (antes) | 11 casos · 19 peticiones | **6** |
| Despues de corregir | **17 casos · 25 peticiones** | 0 |

La coleccion completa quedo en verde sin tocar una sola prueba. Eso es lo que
hace que sirvan: si hubiera cambiado el esperado para que pasaran, no habria
demostrado nada.

**Algo que pasaba antes y ahora fallaba.** Al correr tambien la coleccion de la
Semana 14 me encontre con dos pruebas en rojo, las de la peticion 3.2
("Comercios sin token y SIN Accept"), que afirmaban que la respuesta **no** era
JSON y que el codigo **no** era 401.

La guia dice que si algo que pasaba ahora falla, no hay que cambiar la prueba
sino revisar la regla. Lo revise, y el caso resulto ser el otro: la regla esta
bien y la prueba estaba afirmando un comportamiento que decidi cambiar a
proposito en el Laboratorio 4. Antes, sin `Accept`, la API redirigia a la
pantalla de login web; ahora responde 401 en JSON, que es lo correcto bajo
`/api`. Asi que reescribi esas dos pruebas y deje anotado en la coleccion por
que, con el texto de la peticion explicando las dos versiones.

La diferencia entre los dos casos no la decide la prueba, la decide uno leyendo:
una prueba en rojo puede significar "rompiste algo" o "cambiaste algo a
proposito y la prueba no se entero", y confundirlos es como se cuelan los
errores. Lo que nunca es valido es cambiar la prueba hasta que pase sin haber
entendido cual de las dos cosas paso.

---

### Laboratorio 4 — Respuestas de error uniformes y seguras

**Antes de configurar** (`GET /api/prueba-error-bd`, ruta temporal que consulta
una tabla inexistente):

| Peticion | Codigo | Que muestra el cuerpo |
|---|---|---|
| Con `Accept` | **500** | JSON con `SQLSTATE[42S02]: Base table or view not found: 1146 Table 'taskboard_15.tabla_que_no_existe' doesn't exist (Connection: mysql, Host: 127.0.0.1, Port: 3306, Database: taskboard_15, SQL: select * from \`tabla_que_no_existe\`)`, mas la pila de llamadas con las rutas absolutas de mi disco |
| Sin `Accept` | **500** | HTML: la pagina de error de Laravel, con la misma informacion |
| POST invalido sin `Accept` | **302** | Redireccion a `http://127.0.0.1:8000/`, cuerpo HTML, ni un dato del error |

Ahi se ve el problema completo en tres lineas: el nombre de la base, el host, el
puerto, la consulta exacta y la estructura de carpetas del servidor, entregados
a cualquiera que pregunte. No hace falta una vulnerabilidad para abusar de eso:
es el material con el que se empieza a buscar una.

**Despues de configurar `withExceptions`:**

| Peticion | Codigo | Que muestra el cuerpo |
|---|---|---|
| Con `Accept` | **500** | `{"message":"No se pudo guardar la información. Intenta de nuevo más tarde."}` |
| Sin `Accept` | **500** | El mismo JSON (`Content-Type: application/json`) |
| POST invalido sin `Accept` | **422** | JSON con `errors`, ya no la redireccion 302 |

- [x] En `storage/logs/laravel.log` aparece el detalle completo de la
      QueryException, con la consulta y la pila.
- [x] Borre la ruta temporal `/prueba-error-bd` (quedo un comentario en
      `routes/api.php` explicando que estuvo y por que ya no esta).

El detalle no desaparecio: **cambio de destinatario**. Al cliente le toca lo
justo para saber que no fue culpa suya y que puede reintentar; al log le toca
todo, porque ahi si hace falta para arreglar el problema y ahi solo entra quien
tiene acceso al servidor.

**Pregunta de analisis: `shouldRenderJsonWhen` hizo que el encabezado `Accept`
deje de ser obligatorio en `/api`. ¿Por que igual conviene que los clientes lo
sigan enviando?**

Porque lo que arregle fue una consecuencia, no la causa. `shouldRenderJsonWhen`
es una red de seguridad del lado del servidor: evita que un cliente distraido
reciba HTML, pero no cambia lo que el `Accept` significa. Ese encabezado es la
forma en que el cliente dice que entiende, y conviene por cuatro razones.

La primera es que la red solo cubre **mi** servidor: el dia que esta API este
detras de un proxy, un balanceador o un gateway, esas piezas deciden que hacer
con la peticion mirando los encabezados, no mi `bootstrap/app.php`. La segunda es
que el `Accept` es lo unico que permitiria que mañana la misma direccion
devuelva otro formato, un CSV para un reporte por ejemplo, sin romper a nadie:
cada cliente pediria lo que sabe leer. La tercera es que un cliente que no manda
`Accept` esta mal escrito aunque funcione, y funcionar por casualidad es la
peor clase de funcionar: el dia que alguien toque esa configuracion, se cae y
nadie va a entender por que. Y la cuarta es practica: la parte web del mismo
proyecto sigue necesitando el encabezado para distinguir, y acostumbrarse a
mandarlo evita tener dos costumbres distintas segun la ruta.

---

### Laboratorio 5 — Experimento de integridad

Medido con `Transaccion::count()` y `EventoTransaccion::count()` en tinker, con
la base recien sembrada antes de cada paso para que los numeros se puedan
comparar de frente.

| Momento | `Transaccion::count()` | `EventoTransaccion::count()` |
|---|---|---|
| Antes | 4 | 0 |
| Sin `DB::transaction` (Paso A) | **5** | **0** |
| Con `DB::transaction` (Paso B) | **4** | **0** |

Las dos veces el cliente recibio un **500**.

**1. En el Paso A el cliente recibio un 500, pero ¿se guardo algo? ¿Que problema
tiene ese registro?**

Si: quedo una transaccion. El `Transaccion::create()` alcanzo a ejecutarse y la
fila se confirmo sola, antes de que la falla llegara. Lo que no quedo es su
evento de creacion.

El problema no es que haya una fila de mas: es que esa fila **no se puede
explicar**. Es una transaccion sin evento de creacion, o sea un cobro que
existe en la tabla y no existe en la bitacora del sistema. Y ademas nadie sabe
que esta ahi: el cliente recibio un 500, asi que da por hecho que no se guardo
nada y lo mas probable es que reintente, con lo que termina habiendo dos cobros
y solo uno con historia. Del lado del servidor tampoco salta nada, porque la
fila es perfectamente valida: tiene su comercio, su monto y su estado. Es un
dato corrupto que pasa todas las revisiones.

Lo que mas me quedo de esto es que el 500 **miente por omision**. Dice "fallo",
y uno entiende "no paso nada", cuando en realidad paso la mitad.

**2. En el Paso B, ¿por que no quedo nada guardado aunque `Transaccion::create()`
si se ejecuto?**

Porque dentro de `DB::transaction()` el `create()` se ejecuta pero no se
confirma. Todo lo que pasa adentro del closure queda en una transaccion de base
de datos abierta, a la espera: si el closure termina bien, Laravel hace COMMIT y
recien ahi las escrituras se vuelven definitivas; si lanza una excepcion, hace
ROLLBACK y la base vuelve exactamente al punto donde estaba antes de entrar.
La fila llego a existir dentro de esa transaccion, pero nunca fue visible para
nadie mas, y al deshacerse no dejo rastro.

Es la diferencia entre "se ejecuto" y "quedo guardado", que yo tenia confundidas
hasta este experimento.

- [x] Borre el `throw new \RuntimeException(...)` y deje el `store()` con
      `DB::transaction()`.
- [x] CL-01 responde 201 otra vez, y en tinker la transaccion tiene su evento de
      creacion (`5 transacciones / 1 evento`, y la transaccion 6 tiene un evento
      de tipo `creacion`).

---

### Extra — Pruebas de regresion en PHPUnit

```
php artisan test

PASS  Tests\Feature\CrearTransaccionTest
✓ cl02 monto cero responde 422
✓ cl01 monto minimo valido responde 201
✓ e01 monto sobre el maximo
✓ e02 tres decimales
✓ la correccion de e02 no rompe los montos normales
✓ e03 estado prohibido al crear
✓ e04 el mensaje de nombre largo esta traducido
✓ e05 json mal formado responde 400
✓ cl14 cuerpo sin content type responde 415
✓ crear registra el evento de creacion
✓ no se puede crear en el comercio de otro

PASS  Tests\Feature\ExampleTest
✓ la raiz manda al login a quien no tiene sesion

Tests:  13 passed (30 assertions)
```

Son 13 y no 11 porque aproveche para arreglar `ExampleTest`, la prueba de
ejemplo que trae Laravel. Afirmaba que `GET /` responde 200, lo cual dejo de ser
cierto en la Semana 12, cuando la raiz empezo a redirigir al listado detras del
middleware `auth`. O sea que estaba en rojo desde hace tres semanas y nadie se
habia enterado, porque hasta hoy nunca se habia corrido `php artisan test`. Es
un ejemplo chiquito de lo mismo que estamos viendo: el comportamiento cambio y
la prueba no acompaño.

**Reto del extra: quitar `max:99999999.99` y ejecutar `php artisan test`. ¿Que
prueba se pone en rojo?**

Lo hice (con mi valor, `max:999999.99`) y se puso en rojo **exactamente una**:

```
⨯ e01 monto sobre el maximo
Tests:  1 failed, 10 passed
```

Las otras diez siguieron en verde. Eso es lo que hace util a una prueba de
regresion y es lo que me convencio de escribirlas: no avisa solamente que algo
se rompio, sino **que** se rompio. Un solo nombre en rojo apunta directo a la
regla que falta, sin tener que ir probando a mano. Si no existiera esa prueba,
quitar esa linea no habria hecho ruido en ningun lado hasta que un cliente
mandara un monto grande en produccion.

---

### Bitacora de aprendizaje del viernes

**¿Que te enseño el experimento de integridad sobre los errores que "no se ven"?
Describe una situacion de una pasarela de pagos donde un registro a medias
causaria un problema real.**

Me enseño que un error puede estar completamente a la vista por un lado y ser
invisible por el otro. En el Paso A el cliente recibio un 500 bien claro, y aun
asi el error de verdad quedo escondido: una transaccion guardada sin su evento
de creacion, que nadie iba a buscar porque los dos lados creian saber que habia
pasado. El cliente creia que no se guardo nada, y el servidor registro una falla
sin registrar que antes de fallar ya habia escrito. Lo que mas me movio fue
darme cuenta de que la fila huerfana es valida: tiene todos sus campos, cumple
todas las reglas y pasaria cualquier revision. No hay forma de encontrarla
mirando los datos; solo se puede evitar que exista.

Un caso real: un cliente paga en una tienda en linea, la pasarela crea la
transaccion y, antes de registrar el evento que dispara el aviso al comercio, el
servidor se queda sin memoria. El cliente ve "error, intente de nuevo" y vuelve
a pagar, asi que ahora hay dos cobros. El comercio solo recibe el aviso del
segundo, asi que entrega un producto. El primer cobro queda en la base sin
historia: no tiene evento, no aparece en el reporte de conciliacion que se arma
a partir de los eventos, y el dinero esta descontado pero no esta asignado a
ninguna venta. Ese cobro no lo encuentra nadie hasta que el cliente revisa su
estado de cuenta un mes despues y reclama. Y cuando reclama no hay con que
reconstruir que paso, porque justamente lo que falta es el registro.

Con `DB::transaction()` ese caso no existe. O quedan la transaccion y su evento,
o no queda nada y el cliente reintenta sobre una base limpia. "Nada" es un
estado del que se puede salir; "a medias" es uno del que hay que ir a averiguar.
