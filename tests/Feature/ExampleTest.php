<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * La prueba de ejemplo que trae Laravel al crear el proyecto.
 *
 * Venía afirmando que GET / responde 200, que es cierto en un Laravel
 * recién instalado porque ahí la raíz muestra la pantalla de
 * bienvenida. En este proyecto dejó de serlo en la Semana 12: la raíz
 * redirige al listado de comercios y ese listado está detrás del
 * middleware auth, así que quien no tiene sesión termina en /login.
 *
 * O sea que la prueba estaba en rojo desde hace tres semanas y nadie
 * se había enterado, porque hasta hoy nunca se había corrido
 * php artisan test. Es un ejemplo pequeño de lo mismo que la guía
 * llama regresión: el comportamiento cambió y la prueba no acompañó.
 *
 * La corrijo en vez de borrarla, porque esa redirección sí es parte
 * del contrato de la parte web: si mañana alguien le saca el
 * middleware auth al listado, esta prueba lo avisa.
 */
class ExampleTest extends TestCase
{
    public function test_la_raiz_manda_al_login_a_quien_no_tiene_sesion(): void
    {
        $this->get('/')->assertRedirect();

        $this->followingRedirects()
            ->get('/')
            ->assertOk()
            ->assertSee('Iniciar sesión');
    }
}
