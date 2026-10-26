<?php

namespace Tests\Feature;

use App\Models\ConfiguracionOrganizacion;
use App\Models\Notificacion;
use App\Models\Ticket;
use App\Services\CargaTecnicos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActuaComo;
use Tests\TestCase;

/** Los tickets nuevos se asignan solos al técnico con menor carga; el supervisor reasigna; el técnico puede pedirlo. */
class AsignacionAutomaticaTest extends TestCase
{
    use ActuaComo, RefreshDatabase;

    private function crearComoLucia(string $titulo = 'No abre el correo'): Ticket
    {
        $this->como('lucia.vega@andesmarket.pe')->ajax()->post('/tickets', [
            'titulo' => $titulo, 'descripcion' => 'Outlook se cierra al abrirlo.', 'tipo' => 'incidente', 'impacto' => 'bajo', 'urgencia' => 'media',
        ])->assertOk();

        return Ticket::latest('id')->firstOrFail();
    }

    private function orgId(): int
    {
        return $this->usuario('lucia.vega@andesmarket.pe')->organizacion_id;
    }

    private function configurar(array $valores): void
    {
        $config = ConfiguracionOrganizacion::firstOrCreate(['organizacion_id' => $this->orgId()]);
        $config->update(['configuracion' => array_merge($config->configuracion ?? [], $valores)]);
    }

    public function test_ticket_nuevo_se_asigna_al_tecnico_con_menor_carga(): void
    {
        $esperado = app(CargaTecnicos::class)->elegir($this->orgId());
        $ticket = $this->crearComoLucia();

        $this->assertSame($esperado['id'], $ticket->tecnico_id);
        $this->assertSame('asignado', $ticket->estado->value);
        $this->assertTrue($ticket->asignaciones()->whereNull('asignado_por_id')->where('motivo', 'like', 'Asignación automática%')->exists());
        $this->assertTrue(Notificacion::sinFiltroOrganizacion()->where('usuario_id', $esperado['id'])->where('ticket_id', $ticket->id)->where('titulo', 'Ticket asignado')->exists());
        $this->assertTrue(Notificacion::sinFiltroOrganizacion()->where('usuario_id', $ticket->solicitante_id)->where('titulo', 'Tu ticket ya tiene técnico')->exists());
        $this->get("/tickets/{$ticket->codigo}")->assertSee($esperado['nombre'].' revisará tu caso');
    }

    public function test_reparte_entre_tecnicos_segun_la_carga(): void
    {
        $cargas = app(CargaTecnicos::class);
        $antes = $cargas->de($this->orgId())->pluck('activos', 'id');

        foreach (range(1, 4) as $i) {
            $this->crearComoLucia("Incidente de prueba {$i}");
        }

        // Tras repartir, la diferencia entre el más y el menos cargado no crece más de 1 respecto al reparto ideal
        $despues = $cargas->de($this->orgId())->pluck('activos', 'id');
        $this->assertSame($antes->sum() + 4, $despues->sum());
        $this->assertLessThanOrEqual(max(1, $antes->max() - $antes->min()), $despues->max() - $despues->min());
    }

    public function test_con_la_asignacion_automatica_desactivada_avisa_a_la_gestion(): void
    {
        $this->configurar(['asignacion_automatica' => false]);
        $ticket = $this->crearComoLucia();

        $this->assertNull($ticket->tecnico_id);
        $this->assertSame('abierto', $ticket->estado->value);
        $ana = $this->usuario('ana.torres@andesmarket.pe');
        $this->assertTrue(Notificacion::sinFiltroOrganizacion()->where('usuario_id', $ana->id)->where('ticket_id', $ticket->id)->where('titulo', 'Nuevo ticket sin asignar')->exists());
    }

    public function test_avisa_cuando_el_equipo_esta_al_limite_de_carga(): void
    {
        $this->configurar(['carga_maxima' => 1]);
        $ticket = $this->crearComoLucia();

        $ana = $this->usuario('ana.torres@andesmarket.pe');
        $this->assertNotNull($ticket->tecnico_id);
        $this->assertTrue(Notificacion::sinFiltroOrganizacion()->where('usuario_id', $ana->id)->where('ticket_id', $ticket->id)->where('titulo', 'Equipo al límite de carga')->exists());
    }

    public function test_supervisor_reasigna_viendo_la_carga_y_se_notifica_a_ambos(): void
    {
        $ticket = Ticket::where('codigo', 'TKT-2026-00010')->firstOrFail(); // María Peña
        $kevin = $this->usuario('kevin.rojas@andesmarket.pe');

        $this->como('ana.torres@andesmarket.pe')->get("/tickets/{$ticket->codigo}/reasignar")->assertOk()
            ->assertSee('Carga actual del equipo')->assertSee('Kevin Rojas')->assertSee('Actual')->assertSee('tickets activos');

        $this->ajax()->patch("/tickets/{$ticket->codigo}/reasignar", [])->assertUnprocessable()->assertJsonValidationErrors('tecnico_id');
        $this->ajax()->patch("/tickets/{$ticket->codigo}/reasignar", ['tecnico_id' => $kevin->id, 'motivo' => 'María tiene muchos críticos'])->assertOk();

        $ticket->refresh();
        $this->assertSame($kevin->id, $ticket->tecnico_id);
        $this->assertSame('en_progreso', $ticket->estado->value); // el estado se conserva
        $this->assertTrue($ticket->asignaciones()->where('motivo', 'Reasignación: María tiene muchos críticos')->exists());
        $maria = $this->usuario('maria.pena@andesmarket.pe');
        $this->assertTrue(Notificacion::sinFiltroOrganizacion()->where('usuario_id', $maria->id)->where('titulo', 'Ticket reasignado')->exists());
        $this->assertTrue(Notificacion::sinFiltroOrganizacion()->where('usuario_id', $kevin->id)->where('titulo', 'Ticket reasignado a ti')->exists());
        $this->assertTrue(Notificacion::sinFiltroOrganizacion()->where('usuario_id', $ticket->solicitante_id)->where('titulo', 'Cambió el técnico de tu ticket')->exists());
    }

    public function test_tecnico_no_reasigna_pero_puede_pedir_reasignacion(): void
    {
        $ticket = Ticket::where('codigo', 'TKT-2026-00010')->firstOrFail();
        $kevin = $this->usuario('kevin.rojas@andesmarket.pe');
        $this->como('maria.pena@andesmarket.pe');

        $this->ajax()->patch("/tickets/{$ticket->codigo}/reasignar", ['tecnico_id' => $kevin->id])->assertForbidden();
        $this->get("/tickets/{$ticket->codigo}")->assertSee('Pedir reasignación')->assertDontSee('data-accion-ticket="reasignar"', false);

        $this->ajax()->post("/tickets/{$ticket->codigo}/solicitar-reasignacion", [])->assertUnprocessable()->assertJsonValidationErrors('motivo');
        $this->ajax()->post("/tickets/{$ticket->codigo}/solicitar-reasignacion", ['motivo' => 'Tengo 3 críticos en curso'])->assertOk();

        $ana = $this->usuario('ana.torres@andesmarket.pe');
        $this->assertTrue(Notificacion::sinFiltroOrganizacion()->where('usuario_id', $ana->id)->where('titulo', 'Piden reasignar un ticket')->exists());
        $this->assertTrue($ticket->comentarios()->where('es_interno', true)->where('contenido', 'like', 'Solicitó reasignación:%')->exists());
        $this->assertSame($this->usuario('maria.pena@andesmarket.pe')->id, $ticket->fresh()->tecnico_id); // sigue a cargo

        // El supervisor ve el botón de reasignar en el detalle y el panel de carga en el tablero
        $this->como('ana.torres@andesmarket.pe')->get("/tickets/{$ticket->codigo}")->assertSee('data-accion-ticket="reasignar"', false);
        $this->get('/asignaciones')->assertSee('Carga del equipo')->assertSee('María Peña');
    }

    public function test_configuracion_guarda_las_reglas_de_asignacion(): void
    {
        $this->como('rosa.villanueva@andesmarket.pe')->post('/configuracion', [
            'email_activo' => '1', 'push_activo' => '0', 'in_app_activo' => '1',
            'asignacion_automatica' => '0', 'carga_maxima' => '5',
        ])->assertSessionHas('ok');

        $this->assertSame(['automatica' => false, 'maximo' => 5], app(CargaTecnicos::class)->configuracion($this->orgId()));
    }
}
