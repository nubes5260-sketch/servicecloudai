<?php

namespace App\Services;

use App\Enums\EstadoArticulo;
use App\Enums\EstadoIncidencia;
use App\Enums\EstadoTicket;
use App\Enums\Prioridad;
use App\Enums\Rol;
use App\Models\ArticuloConocimiento;
use App\Models\Incidencia;
use App\Models\Ticket;
use App\Models\TicketHistorial;
use App\Models\Usuario;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Métricas del dashboard calculadas desde la BD, según el alcance del rol
 * (el filtro de organización lo aplica el scope de cada modelo).
 */
class DashboardService
{
    private const COLORES = [
        // Tema claro: azul solo como acento; el resto en grises y colores semánticos
        'blue' => '#3B82F6', 'slate' => '#64748B', 'gray' => '#CBD5E1',
        'red' => '#EF4444', 'yellow' => '#F59E0B', 'green' => '#22C55E',
    ];

    private CarbonImmutable $ahora;

    public function __construct()
    {
        $this->ahora = CarbonImmutable::now();
    }

    /** @return array<string, mixed> */
    public function datos(Usuario $usuario): array
    {
        return [
            'kpis' => $this->kpis($usuario),
            'graficos' => $this->graficos($usuario),
            'senales' => $this->senalesOperacion($usuario),
            'actividad' => $this->actividad($usuario),
        ];
    }

    private function tickets(Usuario $usuario): Builder
    {
        return Ticket::query()->visiblesPara($usuario);
    }

    // ------------------------------------------------------------------ KPIs

    private function kpis(Usuario $usuario): array
    {
        $abiertos = $this->tickets($usuario)->abiertos()->count();
        $semana = $this->tickets($usuario)->where('creado_en', '>=', $this->ahora->subDays(7))->count();
        $semanaAnterior = $this->tickets($usuario)->whereBetween('creado_en', [$this->ahora->subDays(14), $this->ahora->subDays(7)])->count();

        // Incidencias críticas activas (o tickets críticos para quien no ve incidencias)
        if ($usuario->puedeVerModulo('incidencias')) {
            $criticas = Incidencia::where('prioridad', Prioridad::Critica)
                ->whereNotIn('estado', [EstadoIncidencia::Resuelta, EstadoIncidencia::Cerrada]);
            $criticasActivas = (clone $criticas)->count();
            $criticasHoy = (clone $criticas)->where('inicio_en', '>=', $this->ahora->startOfDay())->count();
        } else {
            $criticas = $this->tickets($usuario)->abiertos()->where('prioridad', Prioridad::Critica);
            $criticasActivas = (clone $criticas)->count();
            $criticasHoy = (clone $criticas)->where('creado_en', '>=', $this->ahora->startOfDay())->count();
        }

        $vencidos = $this->tickets($usuario)->abiertos()->where('vencimiento_resolucion', '<', $this->ahora)->count();
        $vencenHoy = $this->tickets($usuario)->abiertos()
            ->whereBetween('vencimiento_resolucion', [$this->ahora, $this->ahora->endOfDay()])->count();

        $cumplimiento = $this->cumplimiento($usuario, $this->ahora->subDays(30), $this->ahora);
        $cumplimientoAnterior = $this->cumplimiento($usuario, $this->ahora->subDays(60), $this->ahora->subDays(30));

        $tiempoMedio = $this->tiempoMedioResolucion($usuario, $this->ahora->subDays(30), $this->ahora);
        $tiempoMedioAnterior = $this->tiempoMedioResolucion($usuario, $this->ahora->subDays(60), $this->ahora->subDays(30));

        return [
            ['ticket-check', $abiertos, 'Tickets abiertos', $this->variacion($semana, $semanaAnterior).' esta semana', ''],
            ['triangle-alert', $criticasActivas, 'Incidencias críticas', $criticasHoy.' '.($criticasHoy === 1 ? 'nueva' : 'nuevas').' hoy', 'danger'],
            ['timer-off', $vencidos, 'SLA vencidos', $vencenHoy.' '.($vencenHoy === 1 ? 'vence' : 'vencen').' hoy', 'warn'],
            ['shield-check', $cumplimiento === null ? '—' : $this->porcentaje($cumplimiento), 'Cumplimiento SLA',
                $cumplimiento !== null && $cumplimientoAnterior !== null ? sprintf('%+.1f%% mensual', $cumplimiento - $cumplimientoAnterior) : 'Últimos 30 días', 'purple'],
            ['clock-3', $tiempoMedio === null ? '—' : $this->horasMinutos($tiempoMedio), 'Tiempo medio resolución',
                $tiempoMedio !== null && $tiempoMedioAnterior !== null ? sprintf('%+d min', round($tiempoMedio - $tiempoMedioAnterior)) : 'Últimos 30 días', ''],
        ];
    }

    /** % de tickets resueltos dentro del SLA en el período (null si no hay datos). */
    public function cumplimiento(Usuario $usuario, CarbonImmutable $desde, CarbonImmutable $hasta, ?Prioridad $prioridad = null): ?float
    {
        $fila = $this->tickets($usuario)
            ->whereNotNull('resuelto_en')->whereNotNull('vencimiento_resolucion')
            ->whereBetween('resuelto_en', [$desde, $hasta])
            ->when($prioridad, fn ($q) => $q->where('prioridad', $prioridad))
            ->selectRaw('count(*) as total, count(*) filter (where resuelto_en <= vencimiento_resolucion) as cumplidos')
            ->first();

        return $fila && $fila->total > 0 ? round($fila->cumplidos * 100 / $fila->total, 1) : null;
    }

    /** Minutos promedio entre creación y resolución. */
    private function tiempoMedioResolucion(Usuario $usuario, CarbonImmutable $desde, CarbonImmutable $hasta): ?float
    {
        $minutos = $this->tickets($usuario)
            ->whereNotNull('resuelto_en')->whereBetween('resuelto_en', [$desde, $hasta])
            ->selectRaw('avg(extract(epoch from (resuelto_en - creado_en)) / 60) as minutos')
            ->value('minutos');

        return $minutos === null ? null : (float) $minutos;
    }

    // ------------------------------------------------------------- gráficos

    private function graficos(Usuario $usuario): array
    {
        $c = self::COLORES;

        $porEstado = $this->tickets($usuario)->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');
        $estado = fn (EstadoTicket ...$estados) => collect($estados)->sum(fn ($e) => (int) ($porEstado[$e->value] ?? 0));

        $porPrioridad = $this->tickets($usuario)->where('creado_en', '>=', $this->ahora->subDays(30))
            ->selectRaw('prioridad, count(*) as total')->groupBy('prioridad')->pluck('total', 'prioridad');

        $dias = collect(range(6, 0))->map(fn ($i) => $this->ahora->subDays($i)->startOfDay());
        $creados = $this->tickets($usuario)->where('creado_en', '>=', $dias->first())
            ->selectRaw('date(creado_en) as dia, count(*) as total')->groupBy('dia')->pluck('total', 'dia');
        $resueltos = $this->tickets($usuario)->where('resuelto_en', '>=', $dias->first())
            ->selectRaw('date(resuelto_en) as dia, count(*) as total')->groupBy('dia')->pluck('total', 'dia');

        $cumplimiento = $this->cumplimiento($usuario, $this->ahora->subDays(30), $this->ahora);

        return [
            'estado' => [
                'type' => 'doughnut',
                'data' => ['labels' => ['Abiertos', 'En progreso', 'En espera', 'Resueltos'], 'datasets' => [[
                    'data' => [
                        $estado(EstadoTicket::Abierto, EstadoTicket::Asignado),
                        $estado(EstadoTicket::EnProgreso),
                        $estado(EstadoTicket::EnEspera),
                        $estado(EstadoTicket::Resuelto, EstadoTicket::Cerrado),
                    ],
                    'backgroundColor' => [$c['blue'], $c['slate'], $c['yellow'], $c['green']],
                    'borderColor' => '#ffffff', 'borderWidth' => 3, 'hoverOffset' => 4,
                ]]],
                'options' => ['cutout' => '70%'],
            ],
            'prioridad' => [
                'type' => 'bar',
                'data' => ['labels' => ['Crítica', 'Alta', 'Media', 'Baja'], 'datasets' => [[
                    'label' => 'Tickets',
                    'data' => array_map(fn ($p) => (int) ($porPrioridad[$p] ?? 0), ['critica', 'alta', 'media', 'baja']),
                    'backgroundColor' => [$c['red'], $c['yellow'], $c['blue'], $c['gray']],
                    'borderRadius' => 7, 'borderSkipped' => false,
                ]]],
                'options' => ['plugins' => ['legend' => ['display' => false]]],
            ],
            'tendencia' => [
                'type' => 'line',
                'data' => [
                    'labels' => $dias->map(fn ($d) => $d->translatedFormat('j M'))->map(fn ($s) => rtrim(ucfirst($s), '.'))->values()->all(),
                    'datasets' => [
                        ['label' => 'Creados', 'data' => $dias->map(fn ($d) => (int) ($creados[$d->toDateString()] ?? 0))->values()->all(),
                            'borderColor' => $c['blue'], 'backgroundColor' => 'rgba(59,130,246,.08)', 'fill' => true, 'tension' => .35, 'pointRadius' => 2],
                        ['label' => 'Resueltos', 'data' => $dias->map(fn ($d) => (int) ($resueltos[$d->toDateString()] ?? 0))->values()->all(),
                            'borderColor' => $c['slate'], 'backgroundColor' => 'rgba(100,116,139,.06)', 'fill' => true, 'tension' => .35, 'pointRadius' => 2],
                    ],
                ],
            ],
            'sla' => [
                'type' => 'doughnut',
                'data' => ['labels' => ['Cumplido', 'Incumplido'], 'datasets' => [[
                    'data' => $cumplimiento === null ? [0, 0] : [$cumplimiento, round(100 - $cumplimiento, 1)],
                    'backgroundColor' => [$c['green'], $c['red']], 'borderColor' => '#ffffff', 'borderWidth' => 3,
                ]]],
                'options' => ['cutout' => '76%'],
            ],
        ];
    }

    // ------------------------------------------------- señales de la operación

    /** Puntos que requieren atención (panel "Señales de la operación"). */
    private function senalesOperacion(Usuario $usuario): array
    {
        $abiertos = $this->tickets($usuario)->abiertos();

        return [
            [(clone $abiertos)->whereNull('tecnico_id')->count(), 'tickets sin técnico asignado'],
            [(clone $abiertos)->where('estado', EstadoTicket::EnEspera)->count(), 'tickets esperando respuesta del usuario'],
            [(clone $abiertos)->whereNotNull('vencimiento_resolucion')->where('vencimiento_resolucion', '<', $this->ahora)->count(), 'tickets con SLA vencido'],
            [ArticuloConocimiento::where('estado', EstadoArticulo::Publicado)->count(), 'artículos publicados en la base de conocimiento'],
        ];
    }

    // ------------------------------------------------------------- actividad

    /** @return list<array{0: string, 1: string}> */
    private function actividad(Usuario $usuario): array
    {
        $filas = [];

        $enRiesgo = $this->tickets($usuario)->abiertos()->where('vencimiento_resolucion', '>', $this->ahora)
            ->orderBy('vencimiento_resolucion')->first();
        if ($enRiesgo) {
            $filas[] = ['SLA en riesgo', $enRiesgo->codigo.' requiere atención en '.Formato::duracion((int) $this->ahora->diffInMinutes($enRiesgo->vencimiento_resolucion))];
        }

        // Completar con el historial más reciente
        if (count($filas) < 4) {
            $historial = TicketHistorial::with(['ticket:id,codigo', 'usuario:id,nombre'])
                ->whereIn('ticket_id', $this->tickets($usuario)->select('id'))
                ->latest('fecha')->limit(4 - count($filas))->get();
            foreach ($historial as $h) {
                $filas[] = [$h->ticket->codigo.' · '.$this->describirCambio($h), ($h->usuario?->nombre ?? 'Sistema').' · '.Formato::relativo($h->fecha)];
            }
        }

        return array_slice($filas, 0, 4);
    }

    private function describirCambio(TicketHistorial $h): string
    {
        return match ($h->campo) {
            'estado' => 'Estado: '.EstadoTicket::etiquetaDe($h->valor_nuevo),
            'tecnico' => 'Asignado a '.$h->valor_nuevo,
            'prioridad' => 'Prioridad: '.Prioridad::etiquetaDe($h->valor_nuevo),
            'creacion' => 'Ticket creado',
            default => ucfirst($h->campo).' actualizado',
        };
    }

    // ------------------------------------------------------------- formato

    private function variacion(int $actual, int $anterior): string
    {
        // Con una base muy pequeña el porcentaje no dice nada: se muestra la diferencia absoluta
        if ($anterior < 5) {
            return sprintf('%+d', $actual - $anterior);
        }

        return sprintf('%+d%%', round(($actual - $anterior) * 100 / $anterior));
    }

    private function porcentaje(float $valor, int $decimales = 1): string
    {
        $texto = number_format($valor, $decimales, '.', '');

        return (str_contains($texto, '.') ? rtrim(rtrim($texto, '0'), '.') : $texto).'%';
    }

    private function horasMinutos(float $minutos): string
    {
        $minutos = (int) round($minutos);

        return intdiv($minutos, 60).'h '.str_pad((string) ($minutos % 60), 2, '0', STR_PAD_LEFT).'m';
    }
}
