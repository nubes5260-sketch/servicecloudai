<?php

namespace Tests\Feature;

use App\Enums\EstadoContrato;
use App\Models\Activo;
use App\Models\Contrato;
use App\Models\Organizacion;
use App\Models\PoliticaSla;
use App\Models\Servicio;
use App\Services\ContratoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\ActuaComo;
use Tests\TestCase;

class ActivosServiciosContratosTest extends TestCase
{
    use ActuaComo, RefreshDatabase;

    // ------------------------------------------------------------------ Activos

    public function test_listar_activos_por_organizacion(): void
    {
        $this->como('rosa.villanueva@andesmarket.pe')
            ->get('/activos')
            ->assertOk()
            ->assertSee('DB-PROD-01')
            ->assertDontSee('SW-CORE-LIM');
    }

    public function test_crear_activo_con_servicios_de_la_misma_organizacion(): void
    {
        $this->como('rosa.villanueva@andesmarket.pe');
        $erp = Servicio::where('nombre', 'ERP Corporativo')->first();
        $crmNovatech = Servicio::sinFiltroOrganizacion()->where('nombre', 'CRM Cloud')->first();
        $kevin = $this->usuario('kevin.rojas@andesmarket.pe');

        // Servicio de otra organización: error legible en lugar de excepción SQL (FK compuesta)
        $this->ajax()->post('/activos', [
            'nombre' => 'SRV-BACKUP-01', 'tipo' => 'servidor', 'estado' => 'operativo', 'servicios' => [$crmNovatech->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('servicios.0');

        $this->ajax()->post('/activos', [
            'nombre' => 'SRV-BACKUP-01', 'tipo' => 'servidor', 'estado' => 'operativo', 'ip' => '10.20.4.50',
            'responsable_id' => $kevin->id, 'servicios' => [$erp->id],
        ])->assertOk();

        $activo = Activo::where('nombre', 'SRV-BACKUP-01')->firstOrFail();
        $this->assertSame('AST-0006', $activo->codigo);
        $this->assertSame($erp->organizacion_id, $activo->organizacion_id);
        $this->assertSame([$erp->id], $activo->servicios->pluck('id')->all());
        $this->assertFalse(Schema::hasColumn('activos', 'servicio_id')); // la relación vive solo en servicios_activos
    }

    public function test_editar_y_eliminar_activo(): void
    {
        $this->como('rosa.villanueva@andesmarket.pe');
        $activo = Activo::where('nombre', 'PRN-ADM-01')->first();

        $this->ajax()->put("/activos/{$activo->id}", ['nombre' => 'PRN-ADM-01', 'tipo' => 'impresora', 'estado' => 'operativo', 'servicios' => []])->assertOk();
        $this->assertSame('operativo', $activo->fresh()->estado->value);
        $this->assertCount(0, $activo->fresh()->servicios);

        $this->delete("/activos/{$activo->id}")->assertSessionHas('ok');
        $this->assertNull(Activo::find($activo->id));
    }

    public function test_tecnico_ve_activos_pero_no_gestiona_y_solicitante_no_accede(): void
    {
        $activo = Activo::sinFiltroOrganizacion()->where('nombre', 'APP-CRM-02')->first();

        $this->como('luis.ramirez@novatech.pe')->get('/activos')->assertOk()->assertSee('APP-CRM-02');
        $this->get("/activos/{$activo->id}")->assertOk();
        $this->get("/activos/{$activo->id}/edit")->assertForbidden();
        $this->delete("/activos/{$activo->id}")->assertForbidden();

        $this->como('lucia.vega@andesmarket.pe')->get('/activos')->assertForbidden();
    }

    public function test_activo_de_otra_organizacion_da_404(): void
    {
        $activo = Activo::sinFiltroOrganizacion()->where('nombre', 'APP-CRM-02')->first();
        $this->como('rosa.villanueva@andesmarket.pe')->get("/activos/{$activo->id}")->assertNotFound();
        $this->delete("/activos/{$activo->id}")->assertNotFound();
    }

    public function test_exportar_activos(): void
    {
        $csv = $this->como('admin@servicecloud.ai')->get('/activos/exportar?tipo=vm_cloud')->assertOk()->streamedContent();
        $this->assertStringContainsString('APP-CRM-02', $csv);
        $this->assertStringNotContainsString('DB-PROD-01', $csv);
    }

    // ------------------------------------------------------------------ Servicios

    public function test_admin_saas_crea_servicio_validando_relaciones_de_la_organizacion(): void
    {
        $andes = Organizacion::where('codigo', 'ORG-001')->first();
        $politicaNovatech = PoliticaSla::sinFiltroOrganizacion()->whereHas('reglas')->where('organizacion_id', Organizacion::where('codigo', 'ORG-002')->value('id'))->first();
        $politicaAndes = PoliticaSla::sinFiltroOrganizacion()->where('organizacion_id', $andes->id)->where('nombre', 'Gold')->first();

        $this->como('admin@servicecloud.ai')->ajax()->post('/servicios', [
            'organizacion_id' => $andes->id, 'nombre' => 'Portal B2B', 'estado' => 'operativo', 'politica_sla_id' => $politicaNovatech->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('politica_sla_id');

        $this->ajax()->post('/servicios', [
            'organizacion_id' => $andes->id, 'nombre' => 'Portal B2B', 'estado' => 'operativo',
            'politica_sla_id' => $politicaAndes->id, 'disponibilidad_objetivo' => '99.9',
        ])->assertOk();

        $servicio = Servicio::where('nombre', 'Portal B2B')->firstOrFail();
        $this->assertSame($andes->id, $servicio->organizacion_id);
        $this->assertSame('SRV-06', $servicio->codigo);
    }

    public function test_eliminar_servicio_con_tickets_lo_retira(): void
    {
        $this->como('rosa.villanueva@andesmarket.pe');
        $erp = Servicio::where('nombre', 'ERP Corporativo')->first();

        $this->delete("/servicios/{$erp->id}")->assertSessionHas('aviso');
        $this->assertSame('retirado', $erp->fresh()->estado->value);
    }

    public function test_listado_de_servicios_muestra_sla_y_disponibilidad(): void
    {
        $this->como('patricia.gomez@novatech.pe')
            ->get('/servicios')
            ->assertOk()
            ->assertSee('CRM Cloud')
            ->assertSee('99.8')
            ->assertSee('Silver')
            ->assertDontSee('ERP Corporativo');
    }

    // ------------------------------------------------------------------ Contratos

    public function test_crear_contrato_calcula_estado_por_fechas(): void
    {
        $this->como('rosa.villanueva@andesmarket.pe')->ajax()->post('/contratos', [
            'nombre' => 'Soporte extendido', 'fecha_inicio' => now()->subMonths(11)->toDateString(),
            'fecha_fin' => now()->addDays(10)->toDateString(), 'monto' => '15000', 'estado' => 'vigente',
        ])->assertOk();

        $contrato = Contrato::where('nombre', 'Soporte extendido')->firstOrFail();
        $this->assertSame('por_vencer', $contrato->estado->value);
        $this->assertSame('CTR-004', $contrato->codigo);
        $this->assertSame('PEN', $contrato->moneda);
    }

    public function test_validacion_de_fechas_del_contrato(): void
    {
        $this->como('rosa.villanueva@andesmarket.pe')->ajax()->post('/contratos', [
            'nombre' => 'Mal fechado', 'fecha_inicio' => '2026-12-01', 'fecha_fin' => '2026-01-01', 'monto' => '-5', 'estado' => 'vigente',
        ])->assertUnprocessable()->assertJsonValidationErrors(['fecha_fin', 'monto']);
    }

    public function test_listado_de_contratos_con_formato_de_moneda(): void
    {
        $this->como('rosa.villanueva@andesmarket.pe')
            ->get('/contratos')
            ->assertOk()
            ->assertSee('S/ 60,000')
            ->assertSee('01/01/2026')
            ->assertDontSee('Mesa de ayuda Business');
    }

    public function test_comando_actualiza_estados_de_contratos(): void
    {
        $contrato = Contrato::sinFiltroOrganizacion()->where('codigo', 'CTR-001')->first();
        $contrato->update(['fecha_fin' => now()->subDay()->toDateString(), 'estado' => 'vigente']);

        $this->artisan('contratos:actualizar-estados')->assertSuccessful();

        $this->assertSame('vencido', $contrato->fresh()->estado->value);
        $this->assertSame('borrador', Contrato::sinFiltroOrganizacion()->where('codigo', 'CTR-003')->first()->estado->value);
    }

    public function test_estado_por_fechas(): void
    {
        $servicio = new ContratoService;
        $hoy = now();
        $this->assertSame('vigente', $servicio->estadoPorFechas($hoy->copy()->addDays(31), EstadoContrato::Vigente, $hoy)->value);
        $this->assertSame('por_vencer', $servicio->estadoPorFechas($hoy->copy()->addDays(30), EstadoContrato::Vigente, $hoy)->value);
        $this->assertSame('cancelado', $servicio->estadoPorFechas($hoy->copy()->subDays(3), EstadoContrato::Cancelado, $hoy)->value);
    }
}
