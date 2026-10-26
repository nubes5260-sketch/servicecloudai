<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AutenticacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/tickets')->assertRedirect(route('login'));
        $this->getJson('/buscar?q=tkt')->assertUnauthorized();
    }

    public function test_pantalla_de_login_con_identidad_visual(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('ServiceCloud AI')
            ->assertSee('css/servicecloud.css', false)
            ->assertSee('brand-mark', false);
    }

    public function test_login_correcto_registra_ultimo_acceso(): void
    {
        $antes = Usuario::where('email', 'admin@servicecloud.ai')->value('ultimo_acceso');

        $this->post('/login', ['email' => 'admin@servicecloud.ai', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $usuario = Usuario::where('email', 'admin@servicecloud.ai')->first();
        $this->assertTrue($usuario->ultimo_acceso->greaterThan($antes));
    }

    public function test_login_incorrecto(): void
    {
        $this->from('/login')
            ->post('/login', ['email' => 'admin@servicecloud.ai', 'password' => 'incorrecta'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_usuario_inactivo_no_puede_entrar(): void
    {
        $this->post('/login', ['email' => 'diego.campos@horizonte.edu.pe', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Tu usuario está inactivo. Contacta al administrador.']);

        $this->assertGuest();
    }

    public function test_login_limita_intentos(): void
    {
        RateLimiter::clear('admin@servicecloud.ai|127.0.0.1');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'admin@servicecloud.ai', 'password' => 'mal']);
        }

        $this->post('/login', ['email' => 'admin@servicecloud.ai', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout_cierra_la_sesion(): void
    {
        $this->actingAs(Usuario::where('email', 'admin@servicecloud.ai')->first());

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
