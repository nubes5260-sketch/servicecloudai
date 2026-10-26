<?php

namespace Tests\Feature;

use App\Models\ConfiguracionOrganizacion;
use App\Models\Organizacion;
use App\Models\PlanSaas;
use App\Models\PoliticaSla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActuaComo;
use Tests\TestCase;

class OrganizacionesTest extends TestCase
{
    use ActuaComo, RefreshDatabase;

    public function test_admin_saas_lista_las_organizaciones_con_conteos(): void
    {
        $this->como('admin@servicecloud.ai')
            ->get('/organizaciones')
            ->assertOk()
            ->assertSee('AndesMarket S.A.C.')
            ->assertSee('Instituto Horizonte')
            ->assertSee('badge-purple', false); // plan Enterprise
    }

    public function test_admin_de_organizacion_solo_ve_la_suya_y_no_puede_editar(): void
    {
        $andes = Organizacion::where('codigo', 'ORG-001')->first();
        $novatech = Organizacion::where('codigo', 'ORG-002')->first();

        $this->como('rosa.villanueva@andesmarket.pe')
            ->get('/organizaciones')
            ->assertOk()
            ->assertSee('AndesMarket S.A.C.')
            ->assertDontSee('Grupo NovaTech')
            ->assertDontSee('data-modal-titulo="Nuevo · Organizaciones"', false);

        $this->get("/organizaciones/{$novatech->id}")->assertForbidden();
        $this->get("/organizaciones/{$andes->id}/edit")->assertForbidden();
        $this->ajax()->post('/organizaciones', [])->assertForbidden();
    }

    public function test_crear_organizacion_genera_configuracion_y_sla(): void
    {
        $plan = PlanSaas::where('nombre', 'Business')->first();

        $this->como('admin@servicecloud.ai')->ajax()
            ->post('/organizaciones', [
                'razon_social' => 'Clínica San Gabriel S.A.', 'ruc' => '20999888777', 'sector' => 'Salud',
                'plan_id' => $plan->id, 'estado' => 'activa',
            ])
            ->assertOk()
            ->assertJson(['redirect' => route('organizaciones.index')]);

        $org = Organizacion::where('ruc', '20999888777')->firstOrFail();
        $this->assertSame('ORG-004', $org->codigo);
        $this->assertTrue(ConfiguracionOrganizacion::where('organizacion_id', $org->id)->exists());
        $politicas = PoliticaSla::sinFiltroOrganizacion()->where('organizacion_id', $org->id)->with('reglas')->get();
        $this->assertCount(3, $politicas);
        $this->assertCount(4, $politicas->first()->reglas);
        $defecto = ConfiguracionOrganizacion::find($org->id)->configuracion['politica_sla_defecto_id'];
        $this->assertSame('Silver', PoliticaSla::sinFiltroOrganizacion()->find($defecto)->nombre);
    }

    public function test_validacion_devuelve_errores_por_campo(): void
    {
        $this->como('admin@servicecloud.ai')->ajax()
            ->post('/organizaciones', ['ruc' => '123', 'estado' => 'otro'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['razon_social', 'ruc', 'plan_id', 'estado'])
            ->assertJsonPath('errors.razon_social.0', 'El campo organización es obligatorio.')
            ->assertJsonPath('errors.ruc.0', 'El campo RUC debe tener 11 dígitos.');
    }

    public function test_editar_organizacion(): void
    {
        $org = Organizacion::where('codigo', 'ORG-003')->first();

        $this->como('admin@servicecloud.ai')->ajax()
            ->put("/organizaciones/{$org->id}", [
                'razon_social' => 'Instituto Horizonte', 'sector' => 'Educación superior',
                'plan_id' => $org->plan_id, 'estado' => 'activa',
            ])
            ->assertOk();

        $this->assertSame('Educación superior', $org->fresh()->sector);
        $this->assertSame('activa', $org->fresh()->estado->value);
    }

    public function test_eliminar_con_operacion_la_desactiva(): void
    {
        $org = Organizacion::where('codigo', 'ORG-002')->first();

        $this->como('admin@servicecloud.ai')
            ->delete("/organizaciones/{$org->id}")
            ->assertRedirect()
            ->assertSessionHas('aviso');

        $this->assertSame('inactiva', $org->fresh()->estado->value);
    }

    public function test_eliminar_organizacion_sin_operacion(): void
    {
        $this->como('admin@servicecloud.ai')->ajax()->post('/organizaciones', [
            'razon_social' => 'Temporal S.A.C.', 'plan_id' => PlanSaas::first()->id, 'estado' => 'evaluacion',
        ])->assertOk();
        $org = Organizacion::where('razon_social', 'Temporal S.A.C.')->first();

        $this->delete("/organizaciones/{$org->id}")->assertSessionHas('ok');
        $this->assertNull(Organizacion::find($org->id));
    }

    public function test_exportar_csv(): void
    {
        $respuesta = $this->como('admin@servicecloud.ai')->get('/organizaciones/exportar');

        $respuesta->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('servicecloud_organizaciones_', $respuesta->headers->get('content-disposition'));
        $csv = $respuesta->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('AndesMarket S.A.C.', $csv);
    }

    public function test_tecnico_no_accede(): void
    {
        $this->como('luis.ramirez@novatech.pe')->get('/organizaciones')->assertForbidden();
    }
}
