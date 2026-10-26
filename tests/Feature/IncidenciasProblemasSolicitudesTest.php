<?php

namespace Tests\Feature;

use App\Models\Incidencia;
use App\Models\Problema;
use App\Models\Servicio;
use App\Models\Solicitud;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActuaComo;
use Tests\TestCase;

class IncidenciasProblemasSolicitudesTest extends TestCase
{
    use ActuaComo, RefreshDatabase;

    // ------------------------------------------------------------------ Incidencias

    public function test_listado_de_incidencias_por_organizacion(): void
    {
        $this->como('ana.torres@andesmarket.pe')
            ->get('/incidencias')->assertOk()
            ->assertSee('INC-0001')->assertDontSee('INC-0002');
    }

    public function test_crear_incidencia_calcula_prioridad_y_codigo(): void
    {
        $this->como('ana.torres@andesmarket.pe');
        $vpn = Servicio::where('nombre', 'ERP Corporativo')->first();

        $this->ajax()->post('/incidencias', [
            'descripcion' => 'Caída total de VPN', 'servicio_id' => $vpn->id, 'impacto' => 'alto', 'urgencia' => 'alta',
            'estado' => 'investigando', 'responsable_id' => $this->usuario('kevin.rojas@andesmarket.pe')->id,
        ])->assertOk();

        $incidencia = Incidencia::where('descripcion', 'Caída total de VPN')->firstOrFail();
        $this->assertSame('critica', $incidencia->prioridad->value);
        $this->assertSame('INC-0004', $incidencia->codigo);
    }

    public function test_ticket_ya_vinculado_no_puede_repetirse(): void
    {
        $ticket = Ticket::where('codigo', 'TKT-2026-00010')->first(); // ya tiene INC-0001
        $this->como('ana.torres@andesmarket.pe')->ajax()->post('/incidencias', [
            'descripcion' => 'Duplicada', 'impacto' => 'bajo', 'urgencia' => 'baja', 'estado' => 'abierta', 'ticket_id' => $ticket->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('ticket_id');
    }

    public function test_tecnico_actualiza_su_incidencia_pero_no_cambia_responsable(): void
    {
        $incidencia = Incidencia::where('codigo', 'INC-0001')->first(); // de María Peña
        $this->como('maria.pena@andesmarket.pe');

        $datos = ['descripcion' => $incidencia->descripcion, 'impacto' => 'alto', 'urgencia' => 'alta', 'estado' => 'monitoreando'];
        $this->ajax()->put("/incidencias/{$incidencia->id}", $datos + ['responsable_id' => $this->usuario('kevin.rojas@andesmarket.pe')->id])
            ->assertUnprocessable()->assertJsonValidationErrors('responsable_id');
        $this->ajax()->put("/incidencias/{$incidencia->id}", $datos)->assertOk();
        $this->assertSame('monitoreando', $incidencia->fresh()->estado->value);

        $otra = Incidencia::where('codigo', 'INC-0003')->first(); // de Kevin
        $this->get("/incidencias/{$otra->id}/edit")->assertForbidden();
        $this->get('/incidencias/create')->assertForbidden();
    }

    public function test_resolver_incidencia_registra_fecha(): void
    {
        $incidencia = Incidencia::where('codigo', 'INC-0001')->first();
        $this->como('ana.torres@andesmarket.pe')->ajax()->put("/incidencias/{$incidencia->id}", [
            'descripcion' => $incidencia->descripcion, 'impacto' => 'alto', 'urgencia' => 'alta', 'estado' => 'resuelta',
        ])->assertOk();
        $this->assertNotNull($incidencia->fresh()->resuelta_en);
    }

    // ------------------------------------------------------------------ Problemas

    public function test_crear_problema_con_tickets_relacionados(): void
    {
        $this->como('ana.torres@andesmarket.pe');
        $tickets = Ticket::whereIn('codigo', ['TKT-2026-00008', 'TKT-2026-00011'])->pluck('id')->all();

        $this->ajax()->post('/problemas', [
            'titulo' => 'Crecimiento no controlado de logs', 'estado' => 'en_analisis', 'causa_raiz' => 'Rotación de logs deshabilitada',
            'tickets' => $tickets, 'tickets_enviado' => '1',
        ])->assertOk();

        $problema = Problema::where('titulo', 'Crecimiento no controlado de logs')->firstOrFail();
        $this->assertSame('PRB-003', $problema->codigo);
        $this->assertEqualsCanonicalizing($tickets, $problema->tickets->pluck('id')->all());
    }

    public function test_problema_no_acepta_tickets_de_otra_organizacion(): void
    {
        $ajeno = Ticket::sinFiltroOrganizacion()->where('codigo', 'TKT-2026-00009')->first();
        $this->como('ana.torres@andesmarket.pe')->ajax()->post('/problemas', [
            'titulo' => 'X', 'estado' => 'abierto', 'tickets' => [$ajeno->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('tickets.0');
    }

    public function test_solicitante_no_accede_a_problemas(): void
    {
        $this->como('lucia.vega@andesmarket.pe')->get('/problemas')->assertForbidden();
    }

    // ------------------------------------------------------------------ Solicitudes

    public function test_flujo_completo_de_una_solicitud(): void
    {
        $this->como('daniela.paredes@andesmarket.pe')->ajax()
            ->post('/solicitudes', ['titulo' => 'Acceso a SAP Concur', 'prioridad' => 'media'])
            ->assertOk();
        $solicitud = Solicitud::where('titulo', 'Acceso a SAP Concur')->firstOrFail();
        $this->assertSame('pendiente_aprobacion', $solicitud->estado->value);
        $this->assertSame('REQ-0003', $solicitud->codigo);

        // El solicitante no puede aprobarse a sí mismo
        $this->patch("/solicitudes/{$solicitud->id}/aprobar")->assertForbidden();

        $this->como('ana.torres@andesmarket.pe')->patch("/solicitudes/{$solicitud->id}/aprobar")->assertSessionHas('ok');
        $solicitud->refresh();
        $this->assertSame('aprobada', $solicitud->estado->value);
        $this->assertSame($this->usuario('ana.torres@andesmarket.pe')->id, $solicitud->aprobador_id);

        $this->como('kevin.rojas@andesmarket.pe')->patch("/solicitudes/{$solicitud->id}/iniciar")->assertSessionHas('ok');
        $this->patch("/solicitudes/{$solicitud->id}/completar")->assertSessionHas('ok');
        $solicitud->refresh();
        $this->assertSame('completada', $solicitud->estado->value);
        $this->assertNotNull($solicitud->completada_en);
    }

    public function test_solicitante_solo_ve_sus_solicitudes(): void
    {
        $this->como('daniela.paredes@andesmarket.pe')
            ->get('/solicitudes')->assertOk()
            ->assertSee('REQ-0002')       // suya
            ->assertDontSee('REQ-0001');  // de Lucía Vega

        $ajena = Solicitud::where('codigo', 'REQ-0001')->first();
        $this->get("/solicitudes/{$ajena->id}")->assertForbidden();
    }

    public function test_solicitante_cancela_su_solicitud_pendiente(): void
    {
        $solicitud = Solicitud::where('codigo', 'REQ-0001')->first();
        $this->como('lucia.vega@andesmarket.pe')->patch("/solicitudes/{$solicitud->id}/cancelar")->assertSessionHas('aviso');
        $this->assertSame('cancelada', $solicitud->fresh()->estado->value);
    }

    public function test_accion_desconocida_da_404(): void
    {
        $solicitud = Solicitud::where('codigo', 'REQ-0001')->first();
        $this->como('ana.torres@andesmarket.pe')->patch("/solicitudes/{$solicitud->id}/borrar")->assertNotFound();
    }
}
