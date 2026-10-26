<?php

namespace Tests\Feature;

use App\Models\Notificacion;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActuaComo;
use Tests\TestCase;

/** Flujo guiado: técnico pide información, el usuario responde, se resuelve y el usuario confirma o reabre. */
class FlujoTicketTest extends TestCase
{
    use ActuaComo, RefreshDatabase;

    private function ticket(): Ticket
    {
        return Ticket::where('codigo', 'TKT-2026-00010')->firstOrFail(); // Lucía (solicitante) · María (técnico) · en progreso
    }

    private function avisos(int $usuarioId, string $titulo): int
    {
        return Notificacion::sinFiltroOrganizacion()->where('usuario_id', $usuarioId)->where('titulo', $titulo)->count();
    }

    public function test_pedir_informacion_exige_mensaje_y_lo_publica_para_el_usuario(): void
    {
        $ticket = $this->ticket();
        $this->como('maria.pena@andesmarket.pe');

        $this->ajax()->patch("/tickets/{$ticket->codigo}/estado", ['estado' => 'en_espera', 'pedir_info' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('comentario');

        $this->ajax()->patch("/tickets/{$ticket->codigo}/estado", ['estado' => 'en_espera', 'pedir_info' => 1, 'comentario' => '¿Desde qué sede ocurre el error?'])->assertOk();

        $ticket->refresh();
        $this->assertSame('en_espera', $ticket->estado->value);
        $this->assertTrue($ticket->comentarios()->where('es_interno', false)->where('contenido', '¿Desde qué sede ocurre el error?')->exists());
        $this->assertSame(1, $this->avisos($ticket->solicitante_id, 'Necesitamos tu respuesta'));
    }

    public function test_cuando_el_usuario_responde_el_ticket_vuelve_a_en_progreso(): void
    {
        $ticket = $this->ticket();
        $this->como('maria.pena@andesmarket.pe')->ajax()
            ->patch("/tickets/{$ticket->codigo}/estado", ['estado' => 'en_espera', 'pedir_info' => 1, 'comentario' => 'Envíame una captura.'])->assertOk();

        $this->como('lucia.vega@andesmarket.pe')->ajax()
            ->post("/tickets/{$ticket->codigo}/comentarios", ['contenido' => 'Ocurre en la sede Lima, adjunto captura.'])->assertOk();

        $this->assertSame('en_progreso', $ticket->fresh()->estado->value);
        $this->assertSame(1, $this->avisos($ticket->tecnico_id, 'El usuario respondió'));
    }

    public function test_el_solicitante_confirma_la_solucion_y_se_cierra(): void
    {
        $ticket = $this->ticket();
        $this->como('maria.pena@andesmarket.pe')->ajax()
            ->patch("/tickets/{$ticket->codigo}/estado", ['estado' => 'resuelto', 'solucion' => 'Se optimizó la consulta de clientes.'])->assertOk();

        // Solo quien reportó puede confirmar
        $this->como('daniela.paredes@andesmarket.pe')->ajax()->patch("/tickets/{$ticket->codigo}/confirmacion", ['decision' => 'confirmar'])->assertForbidden();
        $this->como('maria.pena@andesmarket.pe')->ajax()->patch("/tickets/{$ticket->codigo}/confirmacion", ['decision' => 'confirmar'])->assertForbidden();

        $this->como('lucia.vega@andesmarket.pe')->get("/tickets/{$ticket->codigo}")->assertOk()
            ->assertSee('Tu ticket fue resuelto')->assertSee('Sí, quedó resuelto')->assertSee('Se optimizó la consulta de clientes.');
        $this->ajax()->patch("/tickets/{$ticket->codigo}/confirmacion", ['decision' => 'confirmar'])->assertOk();

        $ticket->refresh();
        $this->assertSame('cerrado', $ticket->estado->value);
        $this->assertNotNull($ticket->cerrado_en);
        $this->assertSame(1, $this->avisos($ticket->tecnico_id, 'Solución confirmada'));
    }

    public function test_el_solicitante_reabre_explicando_que_sigue_fallando(): void
    {
        $ticket = $this->ticket();
        $this->como('maria.pena@andesmarket.pe')->ajax()
            ->patch("/tickets/{$ticket->codigo}/estado", ['estado' => 'resuelto', 'solucion' => 'Se reinició el servicio.'])->assertOk();

        $this->como('lucia.vega@andesmarket.pe');
        $this->ajax()->patch("/tickets/{$ticket->codigo}/confirmacion", ['decision' => 'reabrir'])
            ->assertUnprocessable()->assertJsonValidationErrors('comentario');
        $this->ajax()->patch("/tickets/{$ticket->codigo}/confirmacion", ['decision' => 'reabrir', 'comentario' => 'Volvió a fallar a las 10:15.'])->assertOk();

        $ticket->refresh();
        $this->assertSame('en_progreso', $ticket->estado->value);
        $this->assertNull($ticket->resuelto_en);
        $this->assertTrue($ticket->comentarios()->where('contenido', 'Volvió a fallar a las 10:15.')->exists());
        $this->assertSame(1, $this->avisos($ticket->tecnico_id, 'El usuario reabrió el ticket'));

        // Ya no está resuelto: no puede volver a confirmarse
        $this->ajax()->patch("/tickets/{$ticket->codigo}/confirmacion", ['decision' => 'confirmar'])->assertForbidden();
    }

    public function test_el_solicitante_ve_su_progreso_sin_datos_internos(): void
    {
        $ticket = $this->ticket();
        $this->como('maria.pena@andesmarket.pe')->ajax()
            ->post("/tickets/{$ticket->codigo}/comentarios", ['contenido' => 'Revisar pool de conexiones (interno)', 'es_interno' => 1])->assertOk();

        $this->como('lucia.vega@andesmarket.pe')->get("/tickets/{$ticket->codigo}")->assertOk()
            ->assertSee('María Peña está atendiendo tu ticket')
            ->assertSee('Progreso del ticket', false)
            ->assertSee('Quién te atiende')
            ->assertDontSee('Ficha del caso')
            ->assertDontSee('Revisar pool de conexiones (interno)');
    }

    public function test_el_tecnico_ve_el_siguiente_paso_con_sus_acciones(): void
    {
        $ticket = $this->ticket();
        $this->como('maria.pena@andesmarket.pe')->get("/tickets/{$ticket->codigo}")->assertOk()
            ->assertSee('Siguiente paso')->assertSee('Marcar como resuelto')->assertSee('Pedir información al usuario')
            ->assertSee('Ficha del caso');

        // Asignado → el paso sugerido es iniciar la atención
        $asignado = Ticket::where('codigo', 'TKT-2026-00012')->firstOrFail(); // Kevin Rojas
        $this->como('kevin.rojas@andesmarket.pe')->get("/tickets/{$asignado->codigo}")->assertSee('Iniciar atención');
        $this->ajax()->patch("/tickets/{$asignado->codigo}/estado", ['estado' => 'en_progreso'])->assertOk();
        $this->assertTrue(Notificacion::sinFiltroOrganizacion()->where('usuario_id', $asignado->solicitante_id)
            ->where('ticket_id', $asignado->id)->where('titulo', 'Están atendiendo tu ticket')->exists());
        $this->assertNotNull($asignado->fresh()->primera_respuesta_en);
    }

    public function test_la_campana_informa_contador_y_notificaciones_nuevas(): void
    {
        $lucia = $this->usuario('lucia.vega@andesmarket.pe');
        $ultima = (int) Notificacion::de($lucia)->max('id');

        $this->como('maria.pena@andesmarket.pe')->ajax()
            ->post('/tickets/TKT-2026-00010/comentarios', ['contenido' => 'Estoy revisando el servidor.'])->assertOk();

        $respuesta = $this->como('lucia.vega@andesmarket.pe')->getJson("/notificaciones/resumen?desde={$ultima}")->assertOk()
            ->assertJsonStructure(['no_leidas', 'ultima', 'html', 'nuevas' => [['id', 'titulo', 'mensaje', 'icono', 'url']]]);
        $this->assertSame('Nuevo mensaje en tu ticket', $respuesta->json('nuevas.0.titulo'));
        $this->assertSame(Notificacion::de($lucia)->noLeidas()->count(), $respuesta->json('no_leidas'));

        // Sin "desde" no se repiten avisos emergentes
        $this->getJson('/notificaciones/resumen')->assertJsonCount(0, 'nuevas');

        // Abrir el ticket marca como leídos sus avisos
        $ticket = $this->ticket();
        $this->get("/tickets/{$ticket->codigo}")->assertOk();
        $this->assertSame(0, Notificacion::de($lucia)->where('ticket_id', $ticket->id)->noLeidas()->count());
    }
}
