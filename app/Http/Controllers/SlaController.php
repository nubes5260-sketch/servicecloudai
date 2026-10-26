<?php

namespace App\Http\Controllers;

use App\Enums\HorarioServicio;
use App\Enums\Prioridad;
use App\Http\Requests\GuardarPoliticaSlaRequest;
use App\Models\ConfiguracionOrganizacion;
use App\Models\Contrato;
use App\Models\Organizacion;
use App\Models\PoliticaSla;
use App\Models\Servicio;
use App\Services\DashboardService;
use App\Services\SlaService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SlaController extends Controller
{
    /** Tarjetas del mockup: etiqueta e ícono por prioridad. */
    private const NIVELES = [
        'critica' => ['Crítico', 'siren', '#EF4444'],
        'alta' => ['Alta', 'triangle-alert', '#F59E0B'],
        'media' => ['Media', 'gauge', '#3B82F6'],
        'baja' => ['Baja', 'circle-check-big', '#22C55E'],
    ];

    public function __construct(private DashboardService $metricas, private SlaService $sla) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PoliticaSla::class);
        $usuario = $request->user();

        $politicas = PoliticaSla::query()->with(['reglas', 'organizacion:id,razon_social'])
            ->withCount(['reglas'])
            ->orderByRaw('organizacion_id nulls first')->orderBy('nombre')->get();

        $seleccionada = $politicas->firstWhere('id', (int) $request->query('politica'))
            ?? $this->politicaPorDefecto($usuario->organizacion_id, $politicas)
            ?? $politicas->first();

        $desde = CarbonImmutable::now()->subDays(30);
        $niveles = collect(self::NIVELES)->map(function ($nivel, $prioridad) use ($seleccionada, $usuario, $desde) {
            $regla = $seleccionada?->reglas->first(fn ($r) => $r->prioridad->value === $prioridad);

            return [
                'titulo' => $nivel[0], 'icono' => $nivel[1], 'color' => $nivel[2],
                'respuesta' => $regla ? $this->duracion($regla->respuesta_minutos) : '—',
                'resolucion' => $regla ? $this->duracion($regla->resolucion_minutos) : '—',
                'cumplimiento' => $this->metricas->cumplimiento($usuario, $desde, CarbonImmutable::now(), Prioridad::from($prioridad)),
            ];
        });
        $general = $this->metricas->cumplimiento($usuario, $desde, CarbonImmutable::now());
        $valores = $niveles->pluck('cumplimiento')->map(fn ($v) => $v ?? 0)->values();

        return view('sla.index', [
            'politicas' => $politicas,
            'seleccionada' => $seleccionada,
            'niveles' => $niveles,
            'general' => $general,
            'grafico' => [
                'type' => 'bar',
                'data' => ['labels' => ['Crítica', 'Alta', 'Media', 'Baja'], 'datasets' => [[
                    'label' => 'Cumplimiento %', 'data' => $valores,
                    'backgroundColor' => $niveles->pluck('color')->values(), 'borderRadius' => 8, 'borderSkipped' => false,
                ]]],
                'options' => [
                    'plugins' => ['legend' => ['display' => false]],
                    'scales' => [
                        'y' => ['min' => (int) min(70, max(0, floor(($valores->min() ?? 70) / 10) * 10)), 'max' => 100, 'ticks' => ['color' => '#6b7280', 'font' => ['size' => 9]], 'grid' => ['color' => 'rgba(17,24,39,.06)']],
                        'x' => ['ticks' => ['color' => '#6b7280', 'font' => ['size' => 9]], 'grid' => ['display' => false]],
                    ],
                ],
            ],
            'uso' => $this->usoPoliticas($politicas->pluck('id')->all()),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', PoliticaSla::class);
        $politica = new PoliticaSla(['activo' => true]);
        $politica->setRelation('reglas', collect());

        return $this->vistaModal('sla.form', [
            'politica' => $politica,
            'horario' => HorarioServicio::Continuo->value,
            'organizaciones' => $request->user()->esAdminSaas() ? Organizacion::orderBy('razon_social')->pluck('razon_social', 'id')->all() : [],
        ]);
    }

    public function store(GuardarPoliticaSlaRequest $request): JsonResponse|RedirectResponse
    {
        $datos = $request->validated();
        $orgId = $request->user()->esAdminSaas() ? ($datos['organizacion_id'] ?? null) : $request->user()->organizacion_id;

        $politica = DB::transaction(function () use ($datos, $orgId) {
            $politica = PoliticaSla::create(['organizacion_id' => $orgId, 'nombre' => $datos['nombre'], 'descripcion' => $datos['descripcion'] ?? null, 'activo' => $datos['activo']]);
            $this->guardarReglas($politica, $datos);

            return $politica;
        });

        return $this->guardado(route('sla.index', ['politica' => $politica->id]), ['Política SLA creada', "{$politica->nombre}: reglas de respuesta y resolución guardadas."]);
    }

    public function edit(PoliticaSla $politica): View
    {
        $this->authorize('update', $politica);
        $politica->load('reglas');

        return $this->vistaModal('sla.form', [
            'politica' => $politica,
            'horario' => $politica->reglas->first()?->horario_servicio->value ?? HorarioServicio::Continuo->value,
            'organizaciones' => [],
        ]);
    }

    public function update(GuardarPoliticaSlaRequest $request, PoliticaSla $politica): JsonResponse|RedirectResponse
    {
        $datos = $request->validated();
        DB::transaction(function () use ($politica, $datos) {
            $politica->update(['nombre' => $datos['nombre'], 'descripcion' => $datos['descripcion'] ?? null, 'activo' => $datos['activo']]);
            $this->guardarReglas($politica, $datos);
        });

        return $this->guardado(route('sla.index', ['politica' => $politica->id]), ['Política SLA actualizada', 'Los nuevos tickets usarán estos tiempos.']);
    }

    public function destroy(PoliticaSla $politica): RedirectResponse
    {
        $this->authorize('delete', $politica);
        $usos = $this->usoPoliticas([$politica->id])[$politica->id] ?? 0;
        if ($usos > 0) {
            $politica->update(['activo' => false]);

            return redirect()->route('sla.index')->with('aviso', ['Política desactivada', "{$politica->nombre} está en uso por {$usos} registros; se desactivó en lugar de eliminarse."]);
        }
        $politica->delete();

        return redirect()->route('sla.index')->with('ok', ['Política eliminada', "{$politica->nombre} fue eliminada."]);
    }

    private function guardarReglas(PoliticaSla $politica, array $datos): void
    {
        foreach (Prioridad::valores() as $prioridad) {
            $politica->reglas()->updateOrCreate(['prioridad' => $prioridad], [
                'respuesta_minutos' => (int) $datos['reglas'][$prioridad]['respuesta'],
                'resolucion_minutos' => (int) $datos['reglas'][$prioridad]['resolucion'],
                'horario_servicio' => $datos['horario_servicio'],
            ]);
        }
    }

    private function politicaPorDefecto(?int $organizacionId, $politicas): ?PoliticaSla
    {
        if (! $organizacionId) {
            return null;
        }
        $id = ConfiguracionOrganizacion::find($organizacionId)?->configuracion['politica_sla_defecto_id'] ?? null;

        return $politicas->firstWhere('id', $id);
    }

    /** @return array<int, int> política => tickets + servicios + contratos que la usan */
    private function usoPoliticas(array $ids): array
    {
        $uso = array_fill_keys($ids, 0);
        // Tickets: la política sale de su regla SLA vigente (tickets_sla → reglas_sla)
        $porTickets = DB::table('tickets_sla')->join('reglas_sla', 'reglas_sla.id', '=', 'tickets_sla.regla_sla_id')
            ->whereIn('reglas_sla.politica_sla_id', $ids)
            ->selectRaw('reglas_sla.politica_sla_id, count(*) as total')->groupBy('reglas_sla.politica_sla_id')
            ->pluck('total', 'politica_sla_id');
        foreach ($porTickets as $id => $total) {
            $uso[$id] += $total;
        }
        foreach ([Servicio::class, Contrato::class] as $modelo) {
            $conteos = $modelo::sinFiltroOrganizacion()->whereIn('politica_sla_id', $ids)
                ->selectRaw('politica_sla_id, count(*) as total')->groupBy('politica_sla_id')->pluck('total', 'politica_sla_id');
            foreach ($conteos as $id => $total) {
                $uso[$id] += $total;
            }
        }

        return $uso;
    }

    /** 15 min · 2h · 1h 30 min · 3 días */
    private function duracion(int $minutos): string
    {
        if ($minutos < 60) {
            return $minutos.' min';
        }
        if ($minutos >= 2880 && $minutos % 1440 === 0) {
            return ($minutos / 1440).' días';
        }

        return intdiv($minutos, 60).'h'.($minutos % 60 ? ' '.($minutos % 60).' min' : '');
    }
}
