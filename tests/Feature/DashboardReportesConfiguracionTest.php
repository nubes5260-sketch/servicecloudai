<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Models\Auditoria;
use App\Models\ConfiguracionOrganizacion;
use App\Models\Contrato;
use App\Models\Notificacion;
use App\Models\PreferenciaNotificacion;
use App\Models\Ticket;
use App\Services\ReporteService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\ActuaComo;
use Tests\TestCase;

class DashboardReportesConfiguracionTest extends TestCase
{
    use ActuaComo, RefreshDatabase;

    // ------------------------------------------------------------------ Dashboard

    public function test_kpis_del_dashboard_respetan_el_alcance_del_rol(): void
    {
        $abiertosGlobal = Ticket::abiertos()->count();
        $andes = $this->usuario('ana.torres@andesmarket.pe')->organizacion_id;
        $abiertosAndes = Ticket::abiertos()->where('organizacion_id', $andes)->count();
        $maria = $this->usuario('maria.pena@andesmarket.pe');
        $abiertosMaria = Ticket::abiertos()->where(fn ($q) => $q->where('tecnico_id', $maria->id)->orWhere('solicitante_id', $maria->id))->count();

        $this->como('admin@servicecloud.ai')->get('/')->assertOk()->assertViewHas('kpis', fn ($kpis) => $kpis[0][1] === $abiertosGlobal);
        $this->como('ana.torres@andesmarket.pe')->get('/')->assertViewHas('kpis', fn ($kpis) => $kpis[0][1] === $abiertosAndes);
        $this->como('maria.pena@andesmarket.pe')->get('/')->assertViewHas('kpis', fn ($kpis) => $kpis[0][1] === $abiertosMaria);
        $this->assertNotSame($abiertosGlobal, $abiertosAndes);
    }

    public function test_dashboard_se_cachea_por_alcance(): void
    {
        $ana = $this->usuario('ana.torres@andesmarket.pe');
        $rosa = $this->usuario('rosa.villanueva@andesmarket.pe');
        $this->assertSame(DashboardController::claveCache($ana), DashboardController::claveCache($rosa)); // misma organización
        $this->assertNotSame(DashboardController::claveCache($ana), DashboardController::claveCache($this->usuario('patricia.gomez@novatech.pe')));

        $this->como('ana.torres@andesmarket.pe')->get('/')->assertOk();
        $this->assertTrue(Cache::has(DashboardController::claveCache($ana)));
    }

    public function test_senales_y_actividad_salen_de_la_bd(): void
    {
        $sinTecnico = Ticket::abiertos()->whereNull('tecnico_id')->count();
        $this->como('ana.torres@andesmarket.pe')->get('/')
            ->assertOk()
            ->assertSee('Señales de la operación')
            ->assertViewHas('senales', fn ($senales) => $senales[0][0] === $sinTecnico && $senales[3][0] >= 1) // artículos publicados
            ->assertViewHas('actividad', fn ($actividad) => count($actividad) >= 3);
    }

    // ------------------------------------------------------------------ Notificaciones

    public function test_centro_de_notificaciones_solo_muestra_las_propias(): void
    {
        $this->como('maria.pena@andesmarket.pe')->get('/notificaciones')
            ->assertOk()->assertSee('Se te asignó TKT-2026-00010.')->assertDontSee('TKT-2026-00007');
    }

    public function test_abrir_notificacion_la_marca_leida_y_lleva_al_ticket(): void
    {
        $maria = $this->usuario('maria.pena@andesmarket.pe');
        $notificacion = Notificacion::where('usuario_id', $maria->id)->whereNotNull('ticket_id')->whereNull('leida_en')->firstOrFail();

        $this->como('maria.pena@andesmarket.pe')->get("/notificaciones/{$notificacion->id}/abrir")
            ->assertRedirect(route('tickets.show', $notificacion->ticket));
        $this->assertNotNull($notificacion->fresh()->leida_en);

        $ajena = Notificacion::sinFiltroOrganizacion()->where('usuario_id', '!=', $maria->id)->first();
        $this->get("/notificaciones/{$ajena->id}/abrir")->assertNotFound();
    }

    public function test_marcar_todas_como_leidas_quita_el_punto_rojo(): void
    {
        $this->como('ana.torres@andesmarket.pe')->get('/')->assertSee('badge-dot', false);
        $this->post('/notificaciones/marcar-todas')->assertSessionHas('ok');

        $this->assertSame(0, Notificacion::de($this->usuario('ana.torres@andesmarket.pe'))->noLeidas()->count());
        $this->get('/')->assertDontSee('<span class="badge-dot"></span>', false);
    }

    // ------------------------------------------------------------------ Reportes

    public function test_los_reportes_descargan_csv(): void
    {
        $this->como('admin@servicecloud.ai')->get('/reportes')->assertOk()->assertSee('Rendimiento de mesa de ayuda')->assertSee('Incidencias críticas');

        foreach (array_keys(ReporteService::REPORTES) as $reporte) {
            $respuesta = $this->get("/reportes/descargar?reporte={$reporte}")->assertOk();
            $this->assertStringContainsString("servicecloud_{$reporte}_", $respuesta->headers->get('content-disposition'));
            $csv = $respuesta->streamedContent();
            $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
            $this->assertGreaterThan(1, substr_count($csv, "\n"), "El reporte {$reporte} debe tener filas");
        }
    }

    public function test_reporte_respeta_la_organizacion_y_las_fechas(): void
    {
        $csv = $this->como('rosa.villanueva@andesmarket.pe')->get('/reportes/descargar?reporte=mesa_ayuda')->assertOk()->streamedContent();
        $this->assertStringContainsString('TKT-2026-00010', $csv);
        $this->assertStringNotContainsString('TKT-2026-00009', $csv);

        $vacio = $this->get('/reportes/descargar?reporte=mesa_ayuda&desde=2020-01-01&hasta=2020-01-31')->streamedContent();
        $this->assertSame(1, substr_count(trim($vacio), "\n") + 1); // solo encabezados
    }

    public function test_reporte_invalido_y_roles_sin_acceso(): void
    {
        $this->como('ana.torres@andesmarket.pe')->get('/reportes/descargar?reporte=inexistente')->assertSessionHasErrors('reporte');
        $this->como('maria.pena@andesmarket.pe')->get('/reportes')->assertForbidden();
    }

    // ------------------------------------------------------------------ Configuración

    public function test_admin_de_organizacion_guarda_la_configuracion(): void
    {
        $rosa = $this->usuario('rosa.villanueva@andesmarket.pe');
        $this->como('rosa.villanueva@andesmarket.pe')->get('/configuracion')->assertOk()->assertSee('Atención y asignación')->assertSee('Auditoría reciente');

        $this->post('/configuracion', [
            'email_activo' => '0', 'push_activo' => '1', 'in_app_activo' => '1', 'carga_maxima' => '6',
        ])->assertRedirect()->assertSessionHas('ok');

        $config = ConfiguracionOrganizacion::find($rosa->organizacion_id);
        $this->assertSame(6, $config->configuracion['carga_maxima']);
        $this->assertFalse(PreferenciaNotificacion::find($rosa->id)->email_activo);
    }

    public function test_tecnico_solo_guarda_sus_preferencias(): void
    {
        $this->como('maria.pena@andesmarket.pe')->get('/configuracion')->assertOk()->assertDontSee('name="carga_maxima"', false);

        $this->post('/configuracion', ['email_activo' => '1', 'push_activo' => '0', 'in_app_activo' => '1', 'carga_maxima' => '6'])
            ->assertSessionHasErrors('carga_maxima');
        $this->post('/configuracion', ['email_activo' => '1', 'push_activo' => '0', 'in_app_activo' => '1'])->assertSessionHas('ok');
        $this->assertFalse(PreferenciaNotificacion::find($this->usuario('maria.pena@andesmarket.pe')->id)->push_activo);
    }

    public function test_sin_notificaciones_en_la_app_no_se_generan(): void
    {
        $maria = $this->usuario('maria.pena@andesmarket.pe');
        PreferenciaNotificacion::where('usuario_id', $maria->id)->update(['in_app_activo' => false]);
        $antes = Notificacion::sinFiltroOrganizacion()->where('usuario_id', $maria->id)->count();

        $ticket = Ticket::where('codigo', 'TKT-2026-00008')->first();
        $this->como('ana.torres@andesmarket.pe')->patchJson("/asignaciones/{$ticket->codigo}", ['columna' => 'asignado']); // sin técnico → error
        app(TicketService::class)->asignar($ticket, $maria->id, $this->usuario('ana.torres@andesmarket.pe'));

        $this->assertSame($antes, Notificacion::sinFiltroOrganizacion()->where('usuario_id', $maria->id)->count());
    }

    public function test_admin_saas_configura_cualquier_organizacion_y_ve_planes(): void
    {
        $novatech = $this->usuario('patricia.gomez@novatech.pe')->organizacion_id;
        $this->como('admin@servicecloud.ai')->get("/configuracion?organizacion={$novatech}")->assertOk()->assertSee('Planes SaaS')->assertSee('Grupo NovaTech');

        $this->post("/configuracion?organizacion={$novatech}", [
            'email_activo' => '1', 'push_activo' => '1', 'in_app_activo' => '1', 'asignacion_automatica' => '0',
        ])->assertSessionHas('ok');
        $this->assertFalse(ConfiguracionOrganizacion::find($novatech)->configuracion['asignacion_automatica']);
    }

    // ------------------------------------------------------------------ Auditoría

    public function test_los_cambios_quedan_auditados_sin_contrasenas(): void
    {
        $contrato = Contrato::where('codigo', 'CTR-001')->first();
        $this->como('rosa.villanueva@andesmarket.pe')->ajax()->put("/contratos/{$contrato->id}", [
            'nombre' => 'Soporte TI Enterprise 2026 (renovado)', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31', 'monto' => '90000', 'estado' => 'vigente',
        ])->assertOk();
        $lucia = $this->usuario('lucia.vega@andesmarket.pe');
        $this->ajax()->put("/usuarios/{$lucia->id}", [
            'nombre' => 'Lucía Vega', 'email' => 'lucia.vega@andesmarket.pe', 'rol' => 'SOLICITANTE', 'estado' => 'activo',
            'password' => 'nuevaClave123', 'password_confirmation' => 'nuevaClave123',
        ])->assertOk();

        $registro = Auditoria::where('entidad', 'contratos')->where('entidad_id', $contrato->id)->latest('id')->firstOrFail();
        $this->assertSame('actualizar', $registro->accion);
        $this->assertSame($this->usuario('rosa.villanueva@andesmarket.pe')->id, $registro->usuario_id);
        $this->assertSame('90000', (string) (int) $registro->datos_despues['monto']);

        $usuario = Auditoria::where('entidad', 'usuarios')->where('entidad_id', $lucia->id)->latest('id')->firstOrFail();
        $this->assertArrayNotHasKey('password_hash', $usuario->datos_despues ?? []);

        $this->get('/configuracion/auditoria?entidad=contratos')->assertOk()->assertSee('contratos');
    }

    public function test_supervisor_no_ve_auditoria(): void
    {
        $this->como('ana.torres@andesmarket.pe')->get('/configuracion/auditoria')->assertForbidden();
    }

    public function test_acciones_sin_usuario_no_se_auditan(): void
    {
        $antes = Auditoria::count();
        Ticket::sinFiltroOrganizacion()->first()->update(['titulo' => 'Cambio desde consola']);
        $this->assertSame($antes, Auditoria::count());
    }
}
