<?php

namespace Tests\Feature;

use App\Enums\EstadoTicket;
use App\Enums\Prioridad;
use App\Enums\Rol;
use App\Models\Activo;
use App\Models\ArticuloConocimiento;
use App\Models\Organizacion;
use App\Models\PoliticaSla;
use App\Models\Problema;
use App\Models\Servicio;
use App\Models\Ticket;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelosTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $email): Usuario
    {
        return Usuario::where('email', $email)->firstOrFail();
    }

    public function test_relaciones_principales_del_ticket_cargan(): void
    {
        $ticket = Ticket::with([
            'organizacion', 'solicitante', 'tecnico', 'servicio', 'activo', 'sla.regla.politica.reglas', 'categoriaTicket',
            'sla.regla', 'historial', 'asignaciones', 'comentarios', 'problemas', 'incidencia',
        ])->where('codigo', 'TKT-2026-00010')->firstOrFail();

        $this->assertSame('AndesMarket S.A.C.', $ticket->organizacion->razon_social);
        $this->assertSame('María Peña', $ticket->tecnico->nombre);
        $this->assertSame('ERP Corporativo', $ticket->servicio->nombre);
        $this->assertSame('Aplicaciones', $ticket->nombreCategoria());
        $this->assertSame(EstadoTicket::EnProgreso, $ticket->estado);
        $this->assertSame(Prioridad::Alta, $ticket->prioridad);
        $this->assertCount(4, $ticket->politicaSla()->reglas); // derivada de tickets_sla → reglas_sla
        $this->assertSame(Prioridad::Alta, $ticket->sla->regla->prioridad);
        $this->assertNotEmpty($ticket->historial);
        $this->assertCount(2, $ticket->comentarios);
        $this->assertSame('PRB-001', $ticket->problemas->first()->codigo);
        $this->assertSame('INC-0001', $ticket->incidencia->codigo);
    }

    public function test_relaciones_n_a_m_de_servicios_activos_y_problemas(): void
    {
        $servicio = Servicio::where('nombre', 'ERP Corporativo')->firstOrFail();
        $this->assertEqualsCanonicalizing(['DB-PROD-01', 'APP-ERP-01'], $servicio->activos->pluck('nombre')->all());

        $activo = Activo::where('nombre', 'DB-PROD-01')->firstOrFail();
        $this->assertEqualsCanonicalizing(['ERP Corporativo', 'Base de datos'], $activo->servicios->pluck('nombre')->all());

        $this->assertSame(1, Problema::where('codigo', 'PRB-002')->firstOrFail()->tickets()->count());
    }

    public function test_helpers_de_rol_del_usuario(): void
    {
        $admin = $this->usuario('admin@servicecloud.ai');
        $this->assertTrue($admin->esAdminSaas());
        $this->assertSame(Rol::AdminSaas, $admin->rolPrincipal());
        $this->assertSame('JE', $admin->iniciales());

        $tecnico = $this->usuario('luis.ramirez@novatech.pe');
        $this->assertFalse($tecnico->esAdminSaas());
        $this->assertTrue($tecnico->tieneRol(Rol::Tecnico));
        $this->assertSame('Técnico de soporte', $tecnico->rolPrincipal()->label());
    }

    public function test_el_scope_oculta_datos_de_otra_organizacion(): void
    {
        $novatech = Organizacion::where('codigo', 'ORG-002')->firstOrFail();
        $totalNovatech = Ticket::where('organizacion_id', $novatech->id)->count();

        $this->actingAs($this->usuario('luis.ramirez@novatech.pe'));

        $this->assertNull(Ticket::where('codigo', 'TKT-2026-00010')->first(), 'Un técnico de NovaTech no debe ver tickets de AndesMarket');
        $this->assertSame($totalNovatech, Ticket::count());
        $this->assertSame(0, Servicio::where('nombre', 'ERP Corporativo')->count());
        $this->assertFalse(Usuario::where('email', 'ana.torres@andesmarket.pe')->exists());
    }

    public function test_admin_saas_ve_todas_las_organizaciones(): void
    {
        $total = Ticket::count();
        $this->actingAs($this->usuario('admin@servicecloud.ai'));

        $this->assertSame($total, Ticket::count());
        $this->assertTrue(Ticket::where('codigo', 'TKT-2026-00010')->exists());
    }

    public function test_articulos_publicos_visibles_y_de_otra_organizacion_ocultos(): void
    {
        $this->actingAs($this->usuario('luis.ramirez@novatech.pe'));

        $codigos = ArticuloConocimiento::pluck('codigo');
        $this->assertContains('KB-001', $codigos);   // público de plataforma
        $this->assertContains('KB-008', $codigos);   // de NovaTech
        $this->assertNotContains('KB-007', $codigos); // de AndesMarket

        // Plantillas SLA globales visibles junto a las propias
        $this->assertTrue(PoliticaSla::whereNull('organizacion_id')->exists());
    }

    public function test_al_crear_se_fuerza_la_organizacion_del_usuario(): void
    {
        $tecnico = $this->usuario('luis.ramirez@novatech.pe');
        $andes = Organizacion::where('codigo', 'ORG-001')->firstOrFail();
        $this->actingAs($tecnico);

        $servicio = Servicio::create([
            'organizacion_id' => $andes->id, // intento de escribir en otra organización
            'codigo' => 'SRV-99', 'nombre' => 'Servicio de prueba', 'estado' => 'operativo',
        ]);

        $this->assertSame($tecnico->organizacion_id, $servicio->organizacion_id);
    }
}
