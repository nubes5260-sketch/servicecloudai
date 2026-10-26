<?php

namespace Tests\Feature;

use App\Models\Notificacion;
use App\Models\Servicio;
use App\Models\Ticket;
use App\Models\TicketAsignacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActuaComo;
use Tests\TestCase;

class TicketsTest extends TestCase
{
    use ActuaComo, RefreshDatabase;

    private function crearComoSolicitante(array $extra = []): Ticket
    {
        $this->como('lucia.vega@andesmarket.pe');
        $erp = Servicio::where('nombre', 'ERP Corporativo')->first();
        $this->ajax()->post('/tickets', $extra + [
            'titulo' => 'El ERP no carga el reporte de ventas', 'descripcion' => 'Al abrir el reporte de ventas aparece un error 500.',
            'tipo' => 'incidente', 'servicio_id' => $erp->id, 'impacto' => 'alto', 'urgencia' => 'media',
        ])->assertOk()->assertJsonStructure(['redirect']);

        return Ticket::latest('id')->firstOrFail();
    }

    public function test_crear_ticket_calcula_codigo_prioridad_y_vencimientos_sla(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $ticket = $this->crearComoSolicitante();

        $this->assertMatchesRegularExpression('/^TKT-'.now()->year.'-\d{5}$/', $ticket->codigo);
        $this->assertSame('alta', $ticket->prioridad->value); // alto × media
        $this->assertSame('asignado', $ticket->estado->value); // asignación automática por carga
        $this->assertNotNull($ticket->tecnico_id);
        $this->assertSame($this->usuario('lucia.vega@andesmarket.pe')->id, $ticket->solicitante_id);
        $this->assertSame('Gold', $ticket->politicaSla()->nombre); // política del servicio ERP (vía tickets_sla)
        // Gold · Alta = 30 min respuesta / 240 min resolución (24x7)
        $this->assertEquals(30, $ticket->creado_en->diffInMinutes($ticket->vencimiento_respuesta));
        $this->assertEquals(240, $ticket->creado_en->diffInMinutes($ticket->vencimiento_resolucion));
        $this->assertNotNull($ticket->sla);
        $this->assertSame('creacion', $ticket->historial()->first()->campo);
    }

    public function test_los_codigos_son_correlativos(): void
    {
        $primero = $this->crearComoSolicitante();
        $segundo = $this->crearComoSolicitante();

        $this->assertSame((int) substr($primero->codigo, -5) + 1, (int) substr($segundo->codigo, -5));
    }

    public function test_supervisor_crea_ticket_con_tecnico_y_lo_notifica(): void
    {
        $maria = $this->usuario('maria.pena@andesmarket.pe');
        $lucia = $this->usuario('lucia.vega@andesmarket.pe');

        $this->como('ana.torres@andesmarket.pe')->ajax()->post('/tickets', [
            'titulo' => 'Caída del servicio de facturación', 'descripcion' => 'Nadie puede facturar.', 'tipo' => 'incidente',
            'impacto' => 'alto', 'urgencia' => 'alta', 'tecnico_id' => $maria->id, 'solicitante_id' => $lucia->id, 'origen' => 'telefono',
        ])->assertOk();

        $ticket = Ticket::latest('id')->first();
        $this->assertSame('critica', $ticket->prioridad->value);
        $this->assertSame('asignado', $ticket->estado->value);
        $this->assertSame('telefono', $ticket->origen->value);
        $this->assertTrue(TicketAsignacion::where('ticket_id', $ticket->id)->where('tecnico_nuevo_id', $maria->id)->exists());
        $this->assertTrue(Notificacion::sinFiltroOrganizacion()->where('usuario_id', $maria->id)->where('ticket_id', $ticket->id)->exists());
    }

    public function test_solicitante_no_puede_asignar_ni_priorizar(): void
    {
        $this->como('lucia.vega@andesmarket.pe')->ajax()->post('/tickets', [
            'titulo' => 'X', 'descripcion' => 'Y', 'tipo' => 'consulta', 'impacto' => 'bajo', 'urgencia' => 'baja',
            'tecnico_id' => $this->usuario('maria.pena@andesmarket.pe')->id, 'prioridad' => 'critica',
        ])->assertUnprocessable()->assertJsonValidationErrors(['tecnico_id', 'prioridad']);
    }

    public function test_tecnico_de_otra_organizacion_no_es_valido(): void
    {
        $this->como('ana.torres@andesmarket.pe')->ajax()->post('/tickets', [
            'titulo' => 'X', 'descripcion' => 'Y', 'tipo' => 'incidente', 'impacto' => 'bajo', 'urgencia' => 'baja',
            'tecnico_id' => $this->usuario('luis.ramirez@novatech.pe')->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('tecnico_id');
    }

    public function test_tecnico_solo_ve_sus_tickets(): void
    {
        $this->como('maria.pena@andesmarket.pe');
        $deKevin = Ticket::where('tecnico_id', $this->usuario('kevin.rojas@andesmarket.pe')->id)->first();

        $this->get('/tickets')->assertOk()->assertSee('TKT-2026-00010')->assertDontSee($deKevin->codigo);
        $this->get("/tickets/{$deKevin->codigo}")->assertForbidden();
        $this->get('/tickets/TKT-2026-00009')->assertNotFound(); // otra organización
    }

    public function test_solicitante_no_ve_notas_internas(): void
    {
        $this->como('lucia.vega@andesmarket.pe')
            ->get('/tickets/TKT-2026-00010')
            ->assertOk()
            ->assertSee('más de un año de historial')          // comentario público
            ->assertDontSee('el pool de conexiones está al 92');   // nota interna

        $this->como('ana.torres@andesmarket.pe')
            ->get('/tickets/TKT-2026-00010')
            ->assertSee('el pool de conexiones está al 92');
    }

    public function test_solicitante_no_puede_crear_notas_internas(): void
    {
        $ticket = Ticket::where('codigo', 'TKT-2026-00010')->first();
        $this->como('lucia.vega@andesmarket.pe')->ajax()
            ->post("/tickets/{$ticket->codigo}/comentarios", ['contenido' => 'Sigue fallando', 'es_interno' => '1'])
            ->assertOk();

        $this->assertFalse($ticket->comentarios()->latest('id')->first()->es_interno);
    }

    public function test_en_espera_pausa_el_sla(): void
    {
        $this->travelTo(now()->setTime(9, 0));
        $ticket = $this->crearComoSolicitante();
        $vencimientoInicial = $ticket->vencimiento_resolucion;
        $this->como('ana.torres@andesmarket.pe');
        $ticket->update(['tecnico_id' => $this->usuario('maria.pena@andesmarket.pe')->id, 'estado' => 'asignado']);

        $this->ajax()->patch("/tickets/{$ticket->codigo}/estado", ['estado' => 'en_progreso'])->assertOk();
        $this->ajax()->patch("/tickets/{$ticket->codigo}/estado", ['estado' => 'en_espera'])->assertOk();
        $this->travel(60)->minutes();
        $this->ajax()->patch("/tickets/{$ticket->codigo}/estado", ['estado' => 'en_progreso'])->assertOk();

        $ticket->refresh();
        $this->assertEquals(60, $ticket->sla->minutos_pausados);
        $this->assertEquals(60, $vencimientoInicial->diffInMinutes($ticket->vencimiento_resolucion));
        $this->assertNotNull($ticket->primera_respuesta_en);
    }

    public function test_resolver_exige_solucion_y_respeta_transiciones(): void
    {
        $ticket = Ticket::where('codigo', 'TKT-2026-00010')->first();
        $this->como('maria.pena@andesmarket.pe');

        $this->ajax()->patch("/tickets/{$ticket->codigo}/estado", ['estado' => 'resuelto'])
            ->assertUnprocessable()->assertJsonValidationErrors('solucion');
        $this->ajax()->patch("/tickets/{$ticket->codigo}/estado", ['estado' => 'cerrado'])
            ->assertUnprocessable()->assertJsonValidationErrors('estado');

        $this->ajax()->patch("/tickets/{$ticket->codigo}/estado", ['estado' => 'resuelto', 'solucion' => 'Se creó un índice sobre clientes.fecha_alta.'])->assertOk();
        $ticket->refresh();
        $this->assertSame('resuelto', $ticket->estado->value);
        $this->assertNotNull($ticket->resuelto_en);
        $this->assertNotNull($ticket->sla->resolucion_cumplida);
        $this->assertTrue(Notificacion::sinFiltroOrganizacion()->where('usuario_id', $ticket->solicitante_id)->where('titulo', 'Tu ticket fue resuelto')->exists());
    }

    public function test_cambiar_prioridad_recalcula_el_vencimiento(): void
    {
        $ticket = $this->crearComoSolicitante(); // alta → 240 min
        $this->como('ana.torres@andesmarket.pe')->ajax()->put("/tickets/{$ticket->codigo}", [
            'titulo' => $ticket->titulo, 'descripcion' => $ticket->descripcion, 'tipo' => 'incidente',
            'impacto' => 'alto', 'urgencia' => 'media', 'prioridad' => 'critica',
        ])->assertOk();

        $ticket->refresh();
        $this->assertSame('critica', $ticket->prioridad->value);
        $this->assertEquals(120, $ticket->creado_en->diffInMinutes($ticket->vencimiento_resolucion)); // Gold · Crítica
        $this->assertTrue($ticket->historial()->where('campo', 'prioridad')->exists());
    }

    public function test_adjuntos_se_guardan_en_privado_y_se_descargan_con_permiso(): void
    {
        Storage::fake('local');
        $ticket = Ticket::where('codigo', 'TKT-2026-00010')->first();

        $this->como('lucia.vega@andesmarket.pe')->ajax()->post("/tickets/{$ticket->codigo}/comentarios", [
            'contenido' => 'Adjunto captura', 'adjunto' => UploadedFile::fake()->create('captura.pdf', 120, 'application/pdf'),
        ])->assertOk();

        $adjunto = $ticket->adjuntos()->first();
        Storage::disk('local')->assertExists($adjunto->ruta_storage);
        $this->assertStringStartsWith("tickets/{$ticket->id}/", $adjunto->ruta_storage);
        $this->get("/tickets/{$ticket->codigo}/adjuntos/{$adjunto->id}")->assertOk();

        $this->como('kevin.rojas@andesmarket.pe')->get("/tickets/{$ticket->codigo}/adjuntos/{$adjunto->id}")->assertForbidden();
    }

    public function test_listado_filtra_y_exporta(): void
    {
        $this->como('ana.torres@andesmarket.pe')
            ->get('/tickets?prioridad=alta&q=clientes')
            ->assertOk()->assertSee('TKT-2026-00010')->assertDontSee('TKT-2026-00011');

        $csv = $this->get('/tickets/exportar?estado=en_progreso')->assertOk()->streamedContent();
        $this->assertStringContainsString('TKT-2026-00010', $csv);
        $this->assertStringNotContainsString('TKT-2026-00009', $csv);
    }
}
