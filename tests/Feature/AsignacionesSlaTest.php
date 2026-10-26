<?php

namespace Tests\Feature;

use App\Models\Notificacion;
use App\Models\PoliticaSla;
use App\Models\Ticket;
use App\Models\TicketAsignacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActuaComo;
use Tests\TestCase;

class AsignacionesSlaTest extends TestCase
{
    use ActuaComo, RefreshDatabase;

    public function test_kanban_muestra_las_cinco_columnas(): void
    {
        $this->como('ana.torres@andesmarket.pe')
            ->get('/asignaciones')
            ->assertOk()
            ->assertSeeInOrder(['Sin asignar', 'Asignado', 'En progreso', 'En espera', 'Resuelto'])
            ->assertSee('TKT-2026-00008')
            ->assertDontSee('TKT-2026-00009');
    }

    public function test_tecnico_toma_un_ticket_sin_asignar_y_queda_en_historial(): void
    {
        $ticket = Ticket::where('codigo', 'TKT-2026-00008')->first(); // abierto sin técnico
        $kevin = $this->usuario('kevin.rojas@andesmarket.pe');

        $this->como('kevin.rojas@andesmarket.pe')
            ->patchJson("/asignaciones/{$ticket->codigo}", ['columna' => 'en_progreso'])
            ->assertOk();

        $ticket->refresh();
        $this->assertSame($kevin->id, $ticket->tecnico_id);
        $this->assertSame('en_progreso', $ticket->estado->value);
        $this->assertTrue(TicketAsignacion::where('ticket_id', $ticket->id)->where('tecnico_nuevo_id', $kevin->id)->exists());
        $this->assertTrue($ticket->historial()->where('campo', 'estado')->where('valor_nuevo', 'en_progreso')->exists());
        $this->assertNotNull($ticket->primera_respuesta_en);
    }

    public function test_tecnico_no_mueve_tickets_de_otro(): void
    {
        $ticket = Ticket::where('codigo', 'TKT-2026-00010')->first(); // de María Peña
        $this->como('kevin.rojas@andesmarket.pe')->patchJson("/asignaciones/{$ticket->codigo}", ['columna' => 'en_espera'])->assertForbidden();
    }

    public function test_mover_a_resuelto_pide_solucion(): void
    {
        $ticket = Ticket::where('codigo', 'TKT-2026-00010')->first();
        $this->como('maria.pena@andesmarket.pe');

        $this->patchJson("/asignaciones/{$ticket->codigo}", ['columna' => 'resuelto'])->assertUnprocessable()->assertJsonValidationErrors('solucion');
        $this->patchJson("/asignaciones/{$ticket->codigo}", ['columna' => 'resuelto', 'solucion' => 'Índice creado'])->assertOk();
        $this->assertSame('resuelto', $ticket->fresh()->estado->value);
    }

    public function test_supervisor_no_puede_mover_sin_tecnico(): void
    {
        $ticket = Ticket::where('codigo', 'TKT-2026-00008')->first();
        $this->como('ana.torres@andesmarket.pe')->patchJson("/asignaciones/{$ticket->codigo}", ['columna' => 'en_progreso'])
            ->assertUnprocessable()->assertJsonValidationErrors('columna');
    }

    public function test_autoasignar_reparte_por_menor_carga(): void
    {
        $this->como('ana.torres@andesmarket.pe')->post('/asignaciones/autoasignar')->assertSessionHas('ok');

        $sinAsignar = Ticket::where('estado', 'abierto')->whereNull('tecnico_id')->count();
        $this->assertSame(0, $sinAsignar);
        $ticket = Ticket::where('codigo', 'TKT-2026-00008')->first();
        $this->assertNotNull($ticket->tecnico_id);
        $this->assertSame('asignado', $ticket->estado->value);
    }

    public function test_tecnico_no_puede_autoasignar(): void
    {
        $this->como('maria.pena@andesmarket.pe')->post('/asignaciones/autoasignar')->assertForbidden();
    }

    // ------------------------------------------------------------------ SLA

    public function test_pantalla_sla_muestra_tiempos_y_cumplimiento(): void
    {
        $this->como('ana.torres@andesmarket.pe')
            ->get('/sla')
            ->assertOk()
            ->assertSeeInOrder(['Crítico', '15 min', '2h', 'Alta', '30 min', '4h'])
            ->assertSee('Rendimiento SLA por prioridad')
            ->assertDontSee('id="newSLA"', false); // el supervisor solo ve
    }

    public function test_admin_de_organizacion_crea_politica_con_reglas(): void
    {
        $this->como('rosa.villanueva@andesmarket.pe')->ajax()->post('/sla', [
            'nombre' => 'Platinum', 'activo' => '1', 'horario_servicio' => '24x7',
            'reglas' => [
                'critica' => ['respuesta' => 10, 'resolucion' => 60], 'alta' => ['respuesta' => 20, 'resolucion' => 120],
                'media' => ['respuesta' => 60, 'resolucion' => 240], 'baja' => ['respuesta' => 120, 'resolucion' => 480],
            ],
        ])->assertOk();

        $politica = PoliticaSla::where('nombre', 'Platinum')->firstOrFail();
        $this->assertSame($this->usuario('rosa.villanueva@andesmarket.pe')->organizacion_id, $politica->organizacion_id);
        $this->assertCount(4, $politica->reglas);
    }

    public function test_validacion_resolucion_mayor_que_respuesta(): void
    {
        $this->como('rosa.villanueva@andesmarket.pe')->ajax()->post('/sla', [
            'nombre' => 'Mala', 'activo' => '1', 'horario_servicio' => 'laboral',
            'reglas' => [
                'critica' => ['respuesta' => 60, 'resolucion' => 30], 'alta' => ['respuesta' => 20, 'resolucion' => 120],
                'media' => ['respuesta' => 60, 'resolucion' => 240], 'baja' => ['respuesta' => 120, 'resolucion' => 480],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('reglas.critica.resolucion');
    }

    public function test_admin_de_organizacion_no_edita_plantillas_globales(): void
    {
        $plantilla = PoliticaSla::sinFiltroOrganizacion()->whereNull('organizacion_id')->first();
        $this->como('rosa.villanueva@andesmarket.pe')->get("/sla/{$plantilla->id}/edit")->assertForbidden();
    }

    public function test_eliminar_politica_en_uso_la_desactiva(): void
    {
        $this->como('rosa.villanueva@andesmarket.pe');
        $gold = PoliticaSla::where('nombre', 'Gold')->whereNotNull('organizacion_id')->first();
        $this->delete("/sla/{$gold->id}")->assertSessionHas('aviso');
        $this->assertFalse($gold->fresh()->activo);
    }

    public function test_comando_sla_revisar_avisa_riesgo_y_marca_vencidos(): void
    {
        $ticket = Ticket::sinFiltroOrganizacion()->where('codigo', 'TKT-2026-00010')->first();
        $ticket->update(['vencimiento_resolucion' => now()->addMinutes(20)]);
        $vencido = Ticket::sinFiltroOrganizacion()->where('codigo', 'TKT-2026-00007')->first();
        $vencido->update(['vencimiento_resolucion' => now()->subMinutes(5)]);
        $vencido->sla()->update(['resolucion_cumplida' => null]);

        $this->artisan('sla:revisar')->assertSuccessful();

        $this->assertTrue(Notificacion::sinFiltroOrganizacion()->where('ticket_id', $ticket->id)->where('usuario_id', $ticket->tecnico_id)->where('titulo', 'SLA próximo a vencer')->exists());
        $this->assertFalse($vencido->sla()->first()->resolucion_cumplida);

        // Sin duplicados al volver a ejecutarse
        $antes = Notificacion::sinFiltroOrganizacion()->count();
        $this->artisan('sla:revisar')->assertSuccessful();
        $this->assertSame($antes, Notificacion::sinFiltroOrganizacion()->count());
    }
}
