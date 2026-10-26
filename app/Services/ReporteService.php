<?php

namespace App\Services;

use App\Enums\EstadoIncidencia;
use App\Enums\Prioridad;
use App\Enums\Rol;
use App\Models\Incidencia;
use App\Models\Servicio;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Reportes de la mesa de ayuda (renderReports del mockup) como CSV reales.
 * El alcance lo da el scope de organización (ADMIN_SAAS: global; gestión: su organización).
 */
class ReporteService
{
    public const REPORTES = [
        'mesa_ayuda' => ['file-chart-column', 'Rendimiento de mesa de ayuda', 'KPIs de tickets, tiempos, backlog y resolución.'],
        'sla' => ['timer', 'Cumplimiento SLA', 'Detalle de cumplimiento por prioridad, cliente y servicio.'],
        'servicios' => ['server', 'Disponibilidad de servicios', 'Disponibilidad, degradaciones e incidentes por servicio.'],
        'tecnicos' => ['users-round', 'Productividad técnica', 'Asignaciones, resolución y carga por técnico.'],
        'incidencias' => ['triangle-alert', 'Incidencias críticas', 'Histórico de incidentes de alto impacto y causa raíz.'],
    ];

    public function __construct(private SlaService $sla) {}

    /** @return array{encabezados: list<string>, filas: iterable} */
    public function generar(string $reporte, Usuario $usuario, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        return match ($reporte) {
            'mesa_ayuda' => $this->mesaAyuda($usuario, $desde, $hasta),
            'sla' => $this->sla($usuario, $desde, $hasta),
            'servicios' => $this->servicios($desde, $hasta),
            'tecnicos' => $this->tecnicos($desde, $hasta),
            'incidencias' => $this->incidencias($desde, $hasta),
        };
    }

    private function tickets(Usuario $usuario, CarbonImmutable $desde, CarbonImmutable $hasta)
    {
        return Ticket::visiblesPara($usuario)->whereBetween('creado_en', [$desde, $hasta]);
    }

    private function mesaAyuda(Usuario $usuario, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $filas = $this->tickets($usuario, $desde, $hasta)
            ->with(['organizacion:id,razon_social', 'tecnico:id,nombre', 'servicio:id,nombre', 'categoriaTicket:id,nombre'])
            ->orderBy('creado_en')->lazy()
            ->map(fn (Ticket $t) => [
                $t->codigo, $t->titulo, $t->organizacion?->razon_social, $t->tipo, $t->nombreCategoria(), $t->servicio?->nombre,
                $t->prioridad, $t->estado, $t->origen, $t->tecnico?->nombre ?? 'Sin asignar', $t->creado_en, $t->primera_respuesta_en,
                $t->resuelto_en, $t->resuelto_en ? (int) $t->creado_en->diffInMinutes($t->resuelto_en) : '',
                $t->estaAbierto() ? 'Backlog' : 'Atendido',
            ]);

        return ['encabezados' => ['Código', 'Título', 'Organización', 'Tipo', 'Categoría', 'Servicio', 'Prioridad', 'Estado', 'Canal', 'Técnico',
            'Creado', 'Primera respuesta', 'Resuelto', 'Minutos de resolución', 'Situación'], 'filas' => $filas];
    }

    private function sla(Usuario $usuario, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $filas = $this->tickets($usuario, $desde, $hasta)
            ->with(['organizacion:id,razon_social', 'servicio:id,nombre', 'sla.regla.politica:id,nombre'])
            ->orderBy('creado_en')->lazy()
            ->map(fn (Ticket $t) => [
                $t->codigo, $t->organizacion?->razon_social, $t->servicio?->nombre, $t->prioridad, $t->sla?->regla?->politica?->nombre,
                $t->sla?->regla?->respuesta_minutos, $t->sla?->regla?->resolucion_minutos, $t->sla?->regla?->horario_servicio,
                $t->vencimiento_respuesta, $t->vencimiento_resolucion, $this->siNo($t->sla?->respuesta_cumplida), $this->siNo($t->sla?->resolucion_cumplida),
                $t->sla?->minutos_pausados ?? 0, $this->sla->estadoSla($t)['texto'],
            ]);

        return ['encabezados' => ['Código', 'Organización', 'Servicio', 'Prioridad', 'Política', 'Respuesta (min)', 'Resolución (min)', 'Horario',
            'Vence respuesta', 'Vence resolución', 'Respuesta cumplida', 'Resolución cumplida', 'Minutos en pausa', 'SLA'], 'filas' => $filas];
    }

    private function servicios(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $filas = Servicio::query()
            ->with(['organizacion:id,razon_social', 'politicaSla:id,nombre'])
            ->withCount([
                'tickets as tickets_periodo' => fn ($q) => $q->whereBetween('creado_en', [$desde, $hasta]),
                'incidencias as incidencias_periodo' => fn ($q) => $q->whereBetween('inicio_en', [$desde, $hasta]),
                'incidencias as incidencias_criticas' => fn ($q) => $q->whereBetween('inicio_en', [$desde, $hasta])->whereIn('prioridad', [Prioridad::Critica, Prioridad::Alta]),
            ])
            ->with(['incidencias' => fn ($q) => $q->whereBetween('inicio_en', [$desde, $hasta])])
            ->orderBy('codigo')->get()
            ->map(function (Servicio $s) use ($desde, $hasta) {
                // Disponibilidad estimada: 100 % menos el tiempo con incidencias de prioridad alta/crítica abiertas
                $minutosPeriodo = max(1, $desde->diffInMinutes($hasta));
                $minutosCaida = $s->incidencias->whereIn('prioridad', [Prioridad::Critica, Prioridad::Alta])
                    ->sum(fn (Incidencia $i) => $i->inicio_en->diffInMinutes($i->resuelta_en ?? min(now(), $hasta)));

                return [
                    $s->codigo, $s->nombre, $s->organizacion?->razon_social, $s->estado, $s->politicaSla?->nombre,
                    $s->disponibilidad_objetivo !== null ? $s->disponibilidad_objetivo.'%' : '',
                    round(max(0, 100 - $minutosCaida * 100 / $minutosPeriodo), 2).'%',
                    $s->tickets_periodo, $s->incidencias_periodo, $s->incidencias_criticas, (int) $minutosCaida,
                ];
            });

        return ['encabezados' => ['Código', 'Servicio', 'Organización', 'Estado', 'SLA', 'Disponibilidad objetivo', 'Disponibilidad estimada',
            'Tickets', 'Incidencias', 'Incidencias alta/crítica', 'Minutos con incidencia'], 'filas' => $filas];
    }

    private function tecnicos(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $tecnicos = Usuario::query()
            ->whereHas('roles', fn ($q) => $q->where('codigo', Rol::Tecnico->value))
            ->with('organizacion:id,razon_social')->orderBy('nombre')->get();
        $ids = $tecnicos->pluck('id');

        $estadisticas = Ticket::query()->whereIn('tecnico_id', $ids)->whereBetween('creado_en', [$desde, $hasta])
            ->selectRaw("tecnico_id, count(*) as asignados,
                count(*) filter (where estado in ('resuelto','cerrado')) as resueltos,
                count(*) filter (where estado in ('abierto','asignado','en_progreso','en_espera')) as en_curso,
                avg(extract(epoch from (resuelto_en - creado_en)) / 60) filter (where resuelto_en is not null) as minutos_promedio,
                count(*) filter (where resuelto_en is not null and resuelto_en <= vencimiento_resolucion) as en_sla")
            ->groupBy('tecnico_id')->get()->keyBy('tecnico_id');
        $cargaActual = Ticket::query()->whereIn('tecnico_id', $ids)->abiertos()
            ->selectRaw('tecnico_id, count(*) as total')->groupBy('tecnico_id')->pluck('total', 'tecnico_id');

        $filas = $tecnicos->map(function (Usuario $u) use ($estadisticas, $cargaActual) {
            $e = $estadisticas[$u->id] ?? null;

            return [
                $u->nombre, $u->organizacion?->razon_social, $u->estado, (int) ($e->asignados ?? 0), (int) ($e->resueltos ?? 0), (int) ($e->en_curso ?? 0),
                (int) ($cargaActual[$u->id] ?? 0), $e?->minutos_promedio ? Formato::duracion((int) round($e->minutos_promedio)) : '',
                $e && $e->resueltos > 0 ? round($e->en_sla * 100 / $e->resueltos, 1).'%' : '',
            ];
        });

        return ['encabezados' => ['Técnico', 'Organización', 'Estado', 'Asignados', 'Resueltos', 'En curso', 'Carga actual', 'Tiempo medio de resolución', 'Resueltos dentro del SLA'],
            'filas' => $filas];
    }

    private function incidencias(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $filas = Incidencia::query()
            ->whereIn('prioridad', [Prioridad::Critica, Prioridad::Alta])
            ->whereBetween('inicio_en', [$desde, $hasta])
            ->with(['organizacion:id,razon_social', 'servicio:id,nombre', 'responsable:id,nombre', 'ticket:id,codigo', 'problema:id,codigo,causa_raiz,solucion_temporal'])
            ->orderByDesc('inicio_en')->get()
            ->map(fn (Incidencia $i) => [
                $i->codigo, $i->descripcion, $i->organizacion?->razon_social, $i->servicio?->nombre, $i->impacto, $i->urgencia, $i->prioridad,
                $i->estado, $i->responsable?->nombre, $i->inicio_en, $i->resuelta_en,
                $i->resuelta_en ? (int) $i->inicio_en->diffInMinutes($i->resuelta_en) : '',
                $i->ticket?->codigo, $i->problema?->codigo, $i->problema?->causa_raiz, $i->problema?->solucion_temporal,
                in_array($i->estado, [EstadoIncidencia::Resuelta, EstadoIncidencia::Cerrada], true) ? 'Cerrada' : 'Activa',
            ]);

        return ['encabezados' => ['Código', 'Descripción', 'Organización', 'Servicio', 'Impacto', 'Urgencia', 'Prioridad', 'Estado', 'Responsable',
            'Inicio', 'Resuelta', 'Duración (min)', 'Ticket', 'Problema', 'Causa raíz', 'Workaround', 'Situación'], 'filas' => $filas];
    }

    private function siNo(?bool $valor): string
    {
        return $valor === null ? 'Pendiente' : ($valor ? 'Sí' : 'No');
    }

    /** Resumen para mostrar en las tarjetas (conteo de filas del período). @return array<string, int> */
    public function resumen(Usuario $usuario, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        return [
            'mesa_ayuda' => $this->tickets($usuario, $desde, $hasta)->count(),
            'sla' => $this->tickets($usuario, $desde, $hasta)->whereNotNull('vencimiento_resolucion')->count(),
            'servicios' => Servicio::count(),
            'tecnicos' => Usuario::whereHas('roles', fn ($q) => $q->where('codigo', Rol::Tecnico->value))->count(),
            'incidencias' => Incidencia::whereIn('prioridad', [Prioridad::Critica, Prioridad::Alta])->whereBetween('inicio_en', [$desde, $hasta])->count(),
        ];
    }

    /** @return Collection<string, array{0: string, 1: string, 2: string}> */
    public static function catalogo(): Collection
    {
        return collect(self::REPORTES);
    }
}
