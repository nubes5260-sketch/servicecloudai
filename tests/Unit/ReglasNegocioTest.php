<?php

namespace Tests\Unit;

use App\Enums\EstadoSolicitud;
use App\Enums\EstadoTicket;
use App\Enums\HorarioServicio;
use App\Enums\Impacto;
use App\Enums\Prioridad;
use App\Enums\Urgencia;
use App\Services\SlaService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ReglasNegocioTest extends TestCase
{
    public function test_matriz_impacto_por_urgencia(): void
    {
        $this->assertSame(Prioridad::Critica, Prioridad::desdeMatriz(Impacto::Alto, Urgencia::Alta));
        $this->assertSame(Prioridad::Alta, Prioridad::desdeMatriz(Impacto::Alto, Urgencia::Media));
        $this->assertSame(Prioridad::Media, Prioridad::desdeMatriz(Impacto::Bajo, Urgencia::Alta));
        $this->assertSame(Prioridad::Baja, Prioridad::desdeMatriz(Impacto::Medio, Urgencia::Baja));
    }

    public function test_etiquetas_y_colores_del_mockup(): void
    {
        $this->assertSame('Crítica', Prioridad::Critica->label());
        $this->assertSame('red', Prioridad::Critica->badge());
        $this->assertSame('En progreso', EstadoTicket::EnProgreso->label());
        $this->assertSame('purple', EstadoTicket::EnProgreso->badge());
        $this->assertContains('pendiente_aprobacion', EstadoSolicitud::valores());
    }

    public function test_sla_24x7_suma_minutos_corridos(): void
    {
        $inicio = CarbonImmutable::parse('2026-10-16 23:00');
        $fin = (new SlaService)->sumarMinutos($inicio, 120, HorarioServicio::Continuo);

        $this->assertSame('2026-10-17 01:00', $fin->format('Y-m-d H:i'));
    }

    public function test_sla_laboral_salta_noches_y_fin_de_semana(): void
    {
        $sla = new SlaService;
        // Viernes 17:00 + 120 min laborales = lunes 09:00
        $fin = $sla->sumarMinutos(CarbonImmutable::parse('2026-10-16 17:00'), 120, HorarioServicio::Laboral);
        $this->assertSame('2026-10-19 09:00', $fin->format('Y-m-d H:i'));

        // Sábado: empieza a contar el lunes 08:00
        $fin = $sla->sumarMinutos(CarbonImmutable::parse('2026-10-17 10:00'), 30, HorarioServicio::Laboral);
        $this->assertSame('2026-10-19 08:30', $fin->format('Y-m-d H:i'));

        $this->assertSame(120, $sla->minutosEntre(CarbonImmutable::parse('2026-10-16 17:00'), CarbonImmutable::parse('2026-10-19 09:00'), HorarioServicio::Laboral));
    }
}
