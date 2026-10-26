<?php

namespace App\Services;

use App\Enums\EstadoTicket;
use App\Enums\HorarioServicio;
use App\Enums\Prioridad;
use App\Models\ConfiguracionOrganizacion;
use App\Models\PoliticaSla;
use App\Models\ReglaSla;
use App\Models\Servicio;
use App\Models\Ticket;
use App\Models\TicketSla;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Cálculo y seguimiento de SLA.
 * - Política: servicio → contrato del servicio → política por defecto de la organización → primera activa.
 * - Horario laboral: lunes a viernes de 08:00 a 18:00 (zona APP_TIMEZONE).
 * - "En espera" pausa el reloj: los minutos pausados se suman al vencimiento.
 */
class SlaService
{
    public const INICIO_LABORAL = 8;

    public const FIN_LABORAL = 18;

    /** Minutos antes del vencimiento en que un ticket se considera "en riesgo". */
    public const MINUTOS_RIESGO = 30;

    // ------------------------------------------------------------ políticas

    public function politicaPara(int $organizacionId, ?int $servicioId): ?PoliticaSla
    {
        if ($servicioId) {
            $servicio = Servicio::sinFiltroOrganizacion()->with(['politicaSla', 'contrato.politicaSla'])->find($servicioId);
            if ($servicio?->politicaSla?->activo) {
                return $servicio->politicaSla;
            }
            if ($servicio?->contrato?->politicaSla?->activo) {
                return $servicio->contrato->politicaSla;
            }
        }

        $config = ConfiguracionOrganizacion::sinFiltroOrganizacion()->find($organizacionId);
        $defecto = $config?->configuracion['politica_sla_defecto_id'] ?? null;
        if ($defecto && $politica = PoliticaSla::sinFiltroOrganizacion()->where('organizacion_id', $organizacionId)->where('activo', true)->find($defecto)) {
            return $politica;
        }

        return PoliticaSla::sinFiltroOrganizacion()->where('organizacion_id', $organizacionId)->where('activo', true)->orderBy('id')->first();
    }

    public function reglaPara(?PoliticaSla $politica, Prioridad $prioridad): ?ReglaSla
    {
        return $politica?->reglas()->where('prioridad', $prioridad->value)->first();
    }

    // ------------------------------------------------------------ ciclo de vida del ticket

    /**
     * Elige la regla SLA (política del servicio, del contrato o de la organización) y calcula
     * los vencimientos. No guarda el ticket: la regla queda pendiente para sincronizarRegistro().
     */
    public function aplicar(Ticket $ticket): void
    {
        $politica = $ticket->exists ? $ticket->politicaSla() : null;
        $politica ??= $this->politicaPara($ticket->organizacion_id, $ticket->servicio_id);
        $regla = $this->reglaPara($politica, $ticket->prioridad);
        if (! $regla) {
            return;
        }

        $ticket->reglaSlaPendiente = $regla;
        $this->recalcularVencimientos($ticket, $regla, $ticket->sla?->minutos_pausados ?? 0);
    }

    /** Crea o actualiza la fila tickets_sla (de ahí sale la política del ticket) tras guardarlo. */
    public function sincronizarRegistro(Ticket $ticket): void
    {
        $regla = $ticket->reglaSlaPendiente;
        if (! $regla) {
            return;
        }
        TicketSla::updateOrCreate(['ticket_id' => $ticket->id], ['regla_sla_id' => $regla->id, 'calculado_en' => now()]);
        $ticket->reglaSlaPendiente = null;
        $ticket->unsetRelation('sla');
    }

    /** Recalcula vencimientos desde la creación sumando las pausas (p. ej. tras cambiar la prioridad). */
    public function recalcularVencimientos(Ticket $ticket, ReglaSla $regla, int $minutosPausados = 0): void
    {
        $inicio = $ticket->creado_en ?? now();
        $ticket->vencimiento_respuesta = $this->sumarMinutos($inicio, $regla->respuesta_minutos, $regla->horario_servicio);
        $ticket->vencimiento_resolucion = $this->sumarMinutos($inicio, $regla->resolucion_minutos + $minutosPausados, $regla->horario_servicio);
    }

    /** Reacciona a un cambio de estado: pausa/reanuda y registra cumplimiento. No guarda el ticket. */
    public function alCambiarEstado(Ticket $ticket, EstadoTicket $anterior, EstadoTicket $nuevo): void
    {
        $registro = $ticket->sla()->with('regla')->first();
        if (! $registro) {
            return;
        }
        $ahora = CarbonImmutable::now();
        $horario = $registro->regla->horario_servicio;

        if ($nuevo === EstadoTicket::EnEspera && ! $registro->ultima_pausa_en) {
            $registro->ultima_pausa_en = $ahora;
        }
        if ($anterior === EstadoTicket::EnEspera && $registro->ultima_pausa_en) {
            $registro->minutos_pausados += $this->minutosEntre($registro->ultima_pausa_en, $ahora, $horario);
            $registro->ultima_pausa_en = null;
            $this->recalcularVencimientos($ticket, $registro->regla, $registro->minutos_pausados);
        }

        if ($ticket->primera_respuesta_en && $registro->respuesta_cumplida === null) {
            $registro->respuesta_cumplida = $ticket->primera_respuesta_en <= $ticket->vencimiento_respuesta;
        }
        if ($nuevo === EstadoTicket::Resuelto) {
            $registro->resolucion_cumplida = $ahora <= $ticket->vencimiento_resolucion;
        } elseif ($anterior === EstadoTicket::Resuelto && $nuevo === EstadoTicket::EnProgreso) {
            $registro->resolucion_cumplida = null; // reabierto
        }

        $registro->calculado_en = $ahora;
        $registro->save();
        $ticket->setRelation('sla', $registro);
    }

    // ------------------------------------------------------------ presentación

    /** Minutos de SLA restantes (negativo si vencido). Congelado mientras está en espera. */
    public function minutosRestantes(Ticket $ticket, ?DateTimeInterface $ahora = null): ?int
    {
        if (! $ticket->vencimiento_resolucion) {
            return null;
        }
        $ahora = CarbonImmutable::instance($ahora ?? now());
        $referencia = $ticket->estado === EstadoTicket::EnEspera && $ticket->sla?->ultima_pausa_en
            ? CarbonImmutable::instance($ticket->sla->ultima_pausa_en)
            : $ahora;
        $horario = $ticket->sla?->regla?->horario_servicio ?? HorarioServicio::Continuo;
        $vence = CarbonImmutable::instance($ticket->vencimiento_resolucion);

        return $vence >= $referencia
            ? $this->minutosEntre($referencia, $vence, $horario)
            : -$this->minutosEntre($vence, $referencia, $horario);
    }

    /**
     * Texto y color de la columna SLA (como el mockup): "01h 12m", "Vencido", "Cumplido" (badge verde)
     *
     * @return array{texto: string, tipo: string} tipo: cumplido|incumplido|vencido|riesgo|normal|pausa|sin
     */
    public function estadoSla(Ticket $ticket): array
    {
        if (in_array($ticket->estado, [EstadoTicket::Resuelto, EstadoTicket::Cerrado], true)) {
            $cumplido = $ticket->resuelto_en && $ticket->vencimiento_resolucion && $ticket->resuelto_en <= $ticket->vencimiento_resolucion;

            return $cumplido ? ['texto' => 'Cumplido', 'tipo' => 'cumplido'] : ['texto' => 'Incumplido', 'tipo' => 'incumplido'];
        }
        if ($ticket->estado === EstadoTicket::Cancelado) {
            return ['texto' => '—', 'tipo' => 'sin'];
        }
        $restantes = $this->minutosRestantes($ticket);
        if ($restantes === null) {
            return ['texto' => '—', 'tipo' => 'sin'];
        }
        if ($restantes < 0) {
            return ['texto' => 'Vencido', 'tipo' => 'vencido'];
        }
        $tipo = match (true) {
            $ticket->estado === EstadoTicket::EnEspera => 'pausa',
            $restantes <= self::MINUTOS_RIESGO || $ticket->prioridad === Prioridad::Critica => 'riesgo',
            default => 'normal',
        };

        return ['texto' => Formato::duracion($restantes), 'tipo' => $tipo];
    }

    // ------------------------------------------------------------ calendario

    /** Suma minutos de servicio a una fecha según el horario de la regla. */
    public function sumarMinutos(DateTimeInterface $inicio, int $minutos, HorarioServicio $horario): CarbonImmutable
    {
        $fecha = CarbonImmutable::instance($inicio);
        if ($horario === HorarioServicio::Continuo) {
            return $fecha->addMinutes($minutos);
        }

        $fecha = $this->siguienteMomentoLaboral($fecha);
        while ($minutos > 0) {
            $finDia = $fecha->setTime(self::FIN_LABORAL, 0);
            $disponibles = (int) $fecha->diffInMinutes($finDia);
            if ($minutos <= $disponibles) {
                return $fecha->addMinutes($minutos);
            }
            $minutos -= $disponibles;
            $fecha = $this->siguienteMomentoLaboral($finDia->addMinute());
        }

        return $fecha;
    }

    /** Minutos de servicio transcurridos entre dos fechas según el horario. */
    public function minutosEntre(DateTimeInterface $desde, DateTimeInterface $hasta, HorarioServicio $horario): int
    {
        $desde = CarbonImmutable::instance($desde);
        $hasta = CarbonImmutable::instance($hasta);
        if ($hasta <= $desde) {
            return 0;
        }
        if ($horario === HorarioServicio::Continuo) {
            return (int) $desde->diffInMinutes($hasta);
        }

        $total = 0;
        $cursor = $this->siguienteMomentoLaboral($desde);
        while ($cursor < $hasta) {
            $finDia = $cursor->setTime(self::FIN_LABORAL, 0);
            $tope = $hasta < $finDia ? $hasta : $finDia;
            $total += (int) $cursor->diffInMinutes($tope);
            $cursor = $this->siguienteMomentoLaboral($finDia->addMinute());
        }

        return $total;
    }

    /** Si la fecha cae fuera de horario, avanza a la siguiente apertura. */
    public function siguienteMomentoLaboral(CarbonImmutable $fecha): CarbonImmutable
    {
        if ($fecha->isWeekend()) {
            return $this->siguienteMomentoLaboral($fecha->addDay()->setTime(self::INICIO_LABORAL, 0));
        }
        if ($fecha->hour < self::INICIO_LABORAL) {
            return $fecha->setTime(self::INICIO_LABORAL, 0);
        }
        if ($fecha->hour >= self::FIN_LABORAL) {
            return $this->siguienteMomentoLaboral($fecha->addDay()->setTime(self::INICIO_LABORAL, 0));
        }

        return $fecha;
    }
}
