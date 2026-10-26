<?php

namespace Tests\Feature;

use App\Models\Organizacion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\ActuaComo;
use Tests\TestCase;

class UsuariosTest extends TestCase
{
    use ActuaComo, RefreshDatabase;

    public function test_admin_de_organizacion_lista_solo_su_organizacion(): void
    {
        $this->como('rosa.villanueva@andesmarket.pe')
            ->get('/usuarios')
            ->assertOk()
            ->assertSee('Ana Torres')
            ->assertDontSee('Luis Ramírez')
            ->assertDontSee('Jorge Espinoza');
    }

    public function test_crear_usuario_en_su_organizacion_con_rol(): void
    {
        $andes = Organizacion::where('codigo', 'ORG-001')->first();
        $novatech = Organizacion::where('codigo', 'ORG-002')->first();

        $this->como('rosa.villanueva@andesmarket.pe')->ajax()
            ->post('/usuarios', [
                'organizacion_id' => $novatech->id, // se ignora: está prohibido para no admins
                'nombre' => 'Pedro Quispe', 'email' => 'pedro.quispe@andesmarket.pe', 'rol' => 'TECNICO',
                'estado' => 'activo', 'password' => 'secreta123', 'password_confirmation' => 'secreta123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('organizacion_id');

        $this->ajax()->post('/usuarios', [
            'nombre' => 'Pedro Quispe', 'email' => 'pedro.quispe@andesmarket.pe', 'rol' => 'TECNICO',
            'estado' => 'activo', 'password' => 'secreta123', 'password_confirmation' => 'secreta123',
        ])->assertOk();

        $pedro = Usuario::sinFiltroOrganizacion()->where('email', 'pedro.quispe@andesmarket.pe')->firstOrFail();
        $this->assertSame($andes->id, $pedro->organizacion_id);
        $this->assertTrue($pedro->tieneRol('TECNICO'));
        $this->assertTrue(Hash::check('secreta123', $pedro->password_hash));
        $this->assertNotNull($pedro->preferencias);
    }

    public function test_admin_de_organizacion_no_puede_crear_admin_saas(): void
    {
        $this->como('rosa.villanueva@andesmarket.pe')->ajax()
            ->post('/usuarios', [
                'nombre' => 'Intruso', 'email' => 'intruso@andesmarket.pe', 'rol' => 'ADMIN_SAAS',
                'estado' => 'activo', 'password' => 'secreta123', 'password_confirmation' => 'secreta123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rol');
    }

    public function test_admin_saas_crea_usuario_eligiendo_organizacion(): void
    {
        $logistica = Organizacion::where('codigo', 'ORG-003')->first();

        $this->como('admin@servicecloud.ai')->ajax()->post('/usuarios', [
            'organizacion_id' => $logistica->id, 'nombre' => 'Rocío Díaz', 'email' => 'rocio.diaz@logisticanorte.pe',
            'rol' => 'SUPERVISOR', 'estado' => 'activo', 'password' => 'secreta123', 'password_confirmation' => 'secreta123',
        ])->assertOk();

        $rocio = Usuario::where('email', 'rocio.diaz@logisticanorte.pe')->first();
        $this->assertSame($logistica->id, $rocio->organizacion_id);
        $this->assertTrue($rocio->tieneRol('SUPERVISOR'));
    }

    public function test_editar_sin_cambiar_contrasena_y_validar_email_unico(): void
    {
        $lucia = $this->usuario('lucia.vega@andesmarket.pe');
        $hashAnterior = $lucia->password_hash;

        $this->como('rosa.villanueva@andesmarket.pe')->ajax()
            ->put("/usuarios/{$lucia->id}", ['nombre' => 'Lucía Vega', 'email' => 'ana.torres@andesmarket.pe', 'rol' => 'SOLICITANTE', 'estado' => 'activo'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->ajax()->put("/usuarios/{$lucia->id}", [
            'nombre' => 'Lucía Vega Ríos', 'email' => 'lucia.vega@andesmarket.pe', 'rol' => 'SUPERVISOR', 'estado' => 'activo', 'cargo' => 'Jefa comercial',
        ])->assertOk();

        $lucia = $lucia->fresh();
        $this->assertSame('Lucía Vega Ríos', $lucia->nombre);
        $this->assertSame($hashAnterior, $lucia->password_hash);
        $this->assertTrue($lucia->tieneRol('SUPERVISOR'));
    }

    public function test_eliminar_usuario_con_tickets_lo_desactiva(): void
    {
        $lucia = $this->usuario('lucia.vega@andesmarket.pe');

        $this->como('rosa.villanueva@andesmarket.pe')->delete("/usuarios/{$lucia->id}")->assertSessionHas('aviso');
        $this->assertSame('inactivo', $lucia->fresh()->estado->value);
    }

    public function test_no_puede_eliminarse_a_si_mismo(): void
    {
        $rosa = $this->usuario('rosa.villanueva@andesmarket.pe');
        $this->como('rosa.villanueva@andesmarket.pe')->delete("/usuarios/{$rosa->id}")->assertForbidden();
    }

    public function test_otro_tenant_recibe_404_y_supervisor_solo_lee(): void
    {
        $luis = $this->usuario('luis.ramirez@novatech.pe');
        $this->como('rosa.villanueva@andesmarket.pe')->get("/usuarios/{$luis->id}")->assertNotFound();

        $lucia = $this->usuario('lucia.vega@andesmarket.pe');
        $this->como('ana.torres@andesmarket.pe')->get('/usuarios')->assertOk();
        $this->get("/usuarios/{$lucia->id}/edit")->assertForbidden();
        $this->get('/usuarios/create')->assertForbidden();
    }

    public function test_exportar_csv_respeta_organizacion(): void
    {
        $csv = $this->como('rosa.villanueva@andesmarket.pe')->get('/usuarios/exportar')->assertOk()->streamedContent();

        $this->assertStringContainsString('ana.torres@andesmarket.pe', $csv);
        $this->assertStringNotContainsString('novatech.pe', $csv);
    }
}
