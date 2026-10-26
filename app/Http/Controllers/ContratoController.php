<?php

namespace App\Http\Controllers;

use App\Enums\EstadoContrato;
use App\Http\Requests\GuardarContratoRequest;
use App\Models\Contrato;
use App\Models\Organizacion;
use App\Models\PoliticaSla;
use App\Services\ContratoService;
use App\Support\Codigos;
use App\Support\Csv;
use App\Support\Formato;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContratoController extends Controller
{
    public function __construct(private ContratoService $servicio) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Contrato::class);

        return view('contratos.index', [
            'contratos' => $this->consulta($request)->paginate(config('servicecloud.paginacion'))->withQueryString(),
        ]);
    }

    public function exportar(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Contrato::class);
        $filas = $this->consulta($request)->get()->map(fn (Contrato $c) => [
            $c->codigo, $c->nombre, $c->organizacion?->razon_social, Formato::fecha($c->fecha_inicio), Formato::fecha($c->fecha_fin),
            Formato::moneda($c->monto), $c->estado, $c->politicaSla?->nombre,
        ]);

        return Csv::descargar('contratos', ['ID', 'Contrato', 'Organización', 'Inicio', 'Fin', 'Valor', 'Estado', 'SLA'], $filas);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Contrato::class);
        $contrato = new Contrato(['estado' => EstadoContrato::Vigente, 'fecha_inicio' => now()->startOfDay(), 'fecha_fin' => now()->addYear()->subDay()->startOfDay(), 'monto' => 0]);

        return $this->vistaModal('contratos.form', $this->datosFormulario($request, $contrato));
    }

    public function store(GuardarContratoRequest $request): JsonResponse|RedirectResponse
    {
        $datos = $this->conEstadoCalculado($request->datosConOrganizacion());
        $contrato = DB::transaction(fn () => Contrato::create($datos + ['codigo' => Codigos::siguiente(Contrato::class, 'CTR', 3), 'moneda' => 'PEN']));

        return $this->guardado(route('contratos.index'), ['Registro creado', "Contratos: {$contrato->codigo} · {$contrato->nombre} fue creado."]);
    }

    public function show(Contrato $contrato): View
    {
        $this->authorize('view', $contrato);
        $contrato->load(['organizacion', 'politicaSla', 'servicios:id,contrato_id,nombre']);

        return $this->vistaModal('contratos.show', compact('contrato'));
    }

    public function edit(Request $request, Contrato $contrato): View
    {
        $this->authorize('update', $contrato);

        return $this->vistaModal('contratos.form', $this->datosFormulario($request, $contrato));
    }

    public function update(GuardarContratoRequest $request, Contrato $contrato): JsonResponse|RedirectResponse
    {
        $contrato->update($this->conEstadoCalculado($request->datosConOrganizacion()));

        return $this->guardado(route('contratos.index'), ['Registro actualizado', 'Contratos: cambios guardados correctamente.']);
    }

    public function destroy(Contrato $contrato): RedirectResponse
    {
        $this->authorize('delete', $contrato);

        if ($contrato->servicios()->exists()) {
            $contrato->update(['estado' => EstadoContrato::Cancelado]);

            return back()->with('aviso', ['Contrato cancelado', "{$contrato->codigo} tiene servicios asociados; se marcó como Cancelado."]);
        }
        $contrato->delete();

        return back()->with('ok', ['Registro eliminado', "{$contrato->codigo} fue eliminado."]);
    }

    /** Vigente / por vencer / vencido se ajustan solos a la fecha de fin. */
    private function conEstadoCalculado(array $datos): array
    {
        $datos['estado'] = $this->servicio->estadoPorFechas(Carbon::parse($datos['fecha_fin']), EstadoContrato::from($datos['estado']));

        return $datos;
    }

    private function consulta(Request $request): Builder
    {
        return Contrato::query()
            ->with(['organizacion:id,razon_social', 'politicaSla:id,nombre'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $texto = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('nombre', 'ilike', $texto)->orWhere('codigo', 'ilike', $texto)
                    ->orWhereHas('organizacion', fn ($o) => $o->where('razon_social', 'ilike', $texto)));
            })
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->orderBy('codigo');
    }

    private function datosFormulario(Request $request, Contrato $contrato): array
    {
        return [
            'contrato' => $contrato,
            'organizaciones' => $request->user()->esAdminSaas() && ! $contrato->exists ? Organizacion::orderBy('razon_social')->pluck('razon_social', 'id')->all() : [],
            'politicas' => $this->opcionesRelacion(PoliticaSla::query()->whereNotNull('organizacion_id')->where('activo', true)->orderBy('nombre'), $contrato),
        ];
    }
}
