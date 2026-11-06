<?php

namespace Tests\Feature;

use App\Models\Transaccion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pruebas de regresión de los casos límite de la Semana 15.
 *
 * Cada método de acá abajo es una fila de la bitácora convertida en
 * código, y la diferencia con la colección de Bruno es grande aunque
 * prueben lo mismo:
 *
 *   Bruno          necesita el servidor encendido y la base real, con
 *                  los datos que tenga en ese momento. Sirve para
 *                  explorar y para mostrarle a alguien que funciona.
 *   PHPUnit        no necesita servidor: Laravel arma la petición por
 *                  dentro. La base es SQLite en memoria y nace limpia
 *                  en cada método, así que una prueba nunca depende de
 *                  lo que dejó la anterior. Corre en segundos con un
 *                  solo comando.
 *
 * Las dos se usan, y en momentos distintos: Bruno mientras se busca el
 * error, PHPUnit para que ese error no vuelva nunca sin que alguien se
 * entere. Por eso se llaman pruebas de regresión: regresión es que un
 * error ya corregido vuelva, y esto es lo que lo impide.
 *
 * Prueba de que funcionan: si se quita 'max:999999.99' de la regla del
 * monto, test_e01 se pone en rojo sola.
 */
class CrearTransaccionTest extends TestCase
{
    /**
     * Vacía y vuelve a crear la base en cada método. Es lo que permite
     * que las pruebas corran en cualquier orden sin pisarse.
     */
    use RefreshDatabase;

    /**
     * Lo que toda prueba de acá necesita antes de empezar: los datos
     * del seeder (Ana, Carlos y sus comercios) y una identidad.
     *
     * Sanctum::actingAs() evita tener que hacer login y guardar un
     * token: le dice a la aplicación "de acá en adelante, quien pide
     * es Ana". La petición queda igual de autenticada que con un
     * token real, pero sin la ida y vuelta.
     *
     * array_merge deja un cuerpo válido por defecto y permite que cada
     * prueba cambie solo el campo que le interesa romper. Así, cuando
     * una prueba falla, el único sospechoso es ese campo.
     */
    private bool $preparado = false;

    private function crear(array $datos = [])
    {
        // El seeder corre una sola vez por método de prueba, aunque el
        // método llame a crear() dos veces: volver a sembrar sobre una
        // base ya sembrada choca con el correo único de Ana. La
        // bandera se reinicia sola en cada prueba, porque PHPUnit crea
        // una instancia nueva de la clase para cada una.
        if (! $this->preparado) {
            $this->seed();
            Sanctum::actingAs(User::where('email', 'ana@taskboard.test')->firstOrFail());
            $this->preparado = true;
        }

        return $this->postJson('/api/transacciones', array_merge([
            'comercio_id' => 1,
            'cliente_nombre' => 'Luis Mena',
            'monto' => 25.50,
        ], $datos));
    }

    /**
     * CL-02 · El caso resuelto de la guía: el borde de abajo.
     */
    public function test_cl02_monto_cero_responde_422(): void
    {
        $this->crear(['monto' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('monto');
    }

    /**
     * CL-01 · El otro lado del mismo borde. Va junto al anterior a
     * propósito: una regla de mínimo mal escrita puede rechazar los
     * dos, y sin esta prueba el error pasaría desapercibido.
     */
    public function test_cl01_monto_minimo_valido_responde_201(): void
    {
        $this->crear(['monto' => 0.01])->assertStatus(201);
    }

    /**
     * E-01 · CL-07. El jueves esto respondía 500, porque el monto
     * llegaba hasta el INSERT y MySQL reventaba con "Out of range
     * value for column 'monto'".
     */
    public function test_e01_monto_sobre_el_maximo(): void
    {
        $this->crear(['monto' => 100000000])
            ->assertStatus(422)
            ->assertJsonValidationErrors('monto');
    }

    /**
     * E-02 · CL-09. El jueves esto respondía 201 y guardaba 26.00: la
     * base redondeaba el tercer decimal en silencio y el cliente se
     * quedaba creyendo que cobró 25.999.
     */
    public function test_e02_tres_decimales(): void
    {
        $this->crear(['monto' => 25.999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('monto');
    }

    /**
     * El par del anterior: la regla decimal:0,2 tiene que seguir
     * aceptando montos con uno o con cero decimales.
     *
     * Esta prueba existe por el aviso de la guía: con decimal:2, que
     * exige EXACTAMENTE dos, la corrección de E-02 rompería 25 y 25.5.
     * Sin esta prueba, ese daño no lo detecta nadie.
     */
    public function test_la_correccion_de_e02_no_rompe_los_montos_normales(): void
    {
        $this->crear(['monto' => 25])->assertStatus(201);
        $this->crear(['monto' => 25.5])->assertStatus(201);
    }

    /**
     * E-03 · CL-12. El jueves respondía 201 y descartaba el estado sin
     * avisar, así que el cliente creía haber creado una transacción
     * Completada.
     */
    public function test_e03_estado_prohibido_al_crear(): void
    {
        $this->crear(['estado' => 'Completada'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('estado');
    }

    /**
     * E-04 · CL-10. El código ya era 422; lo que estaba roto era el
     * mensaje, que llegaba como "validation.max.string".
     *
     * La prueba no compara el texto completo: comprueba que NO empiece
     * con "validation.", que es la marca de una clave de idioma sin
     * traducir. Así sigue sirviendo aunque mañana se reescriba el
     * mensaje.
     */
    public function test_e04_el_mensaje_de_nombre_largo_esta_traducido(): void
    {
        $respuesta = $this->crear(['cliente_nombre' => str_repeat('a', 256)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cliente_nombre');

        $this->assertStringNotContainsString(
            'validation.',
            $respuesta->json('errors.cliente_nombre.0')
        );
    }

    /**
     * E-05 · CL-13. Un cuerpo que dice ser JSON y no se puede leer.
     *
     * No se puede usar postJson() acá, porque ese método arma el JSON
     * por su cuenta y nunca mandaría uno inválido. call() permite
     * escribir el cuerpo crudo, que es justo lo que hace falta.
     *
     * El jueves esto respondía 422 diciendo que faltaban los tres
     * campos, que es una pista falsa: los tres campos venían.
     */
    public function test_e05_json_mal_formado_responde_400(): void
    {
        $this->seed();
        Sanctum::actingAs(User::first());

        $this->call('POST', '/api/transacciones', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], '{ "comercio_id": 1, "cliente_nombre": "Luis Mena", "monto": 25.50, }')
            ->assertStatus(400);
    }

    /**
     * CL-14 · El mismo cuerpo, esta vez válido, pero sin decir en qué
     * formato viene. 415 es el código que existe para eso.
     */
    public function test_cl14_cuerpo_sin_content_type_responde_415(): void
    {
        $this->seed();
        Sanctum::actingAs(User::first());

        $this->call('POST', '/api/transacciones', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ], '{ "comercio_id": 1, "cliente_nombre": "Luis Mena", "monto": 25.50 }')
            ->assertStatus(415);
    }

    /**
     * Laboratorio 5 · La otra mitad de DB::transaction().
     *
     * El experimento mostró que si algo falla entre los dos INSERT no
     * queda nada guardado. Esta prueba comprueba lo contrario, que es
     * igual de importante: cuando NO falla nada, las dos escrituras sí
     * quedan. Una transacción de base de datos que hiciera rollback
     * siempre también pasaría la prueba del experimento.
     */
    public function test_crear_registra_el_evento_de_creacion(): void
    {
        $this->crear()->assertStatus(201);

        $id = Transaccion::latest('id')->first()->id;

        $this->assertDatabaseHas('eventos_transaccion', [
            'transaccion_id' => $id,
            'tipo_evento' => 'creacion',
        ]);
    }

    /**
     * El aislamiento de la Semana 14 sigue en pie. Va en este archivo
     * porque una prueba de regresión no solo cuida lo que se corrigió
     * hoy: cuida lo que ya funcionaba.
     */
    public function test_no_se_puede_crear_en_el_comercio_de_otro(): void
    {
        $this->crear(['comercio_id' => 3])
            ->assertStatus(422)
            ->assertJsonValidationErrors('comercio_id');
    }
}
