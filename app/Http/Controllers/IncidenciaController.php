<?php

namespace App\Http\Controllers;

use App\Enums\EstadoIncidencia;
use App\Enums\Impacto;
use App\Enums\Prioridad;
use App\Enums\Urgencia;
use App\Http\Requests\GuardarIncidenciaRequest;
use App\Models\Incidencia;
use App\Models\Organizacion;
use App\Models\Problema;
use App\Models\Servicio;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Support\Codigos;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncidenciaController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Incidencia::class);

        return view('incidencias.index', [
            'incidencias' => $this->consulta($request)->paginate(config('servicecloud.paginacion'))->withQueryString(),
        ]);
    }

    public function exportar(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Incidencia::class);
        $filas = $this->consulta($request)->with(['ticket:id,codigo', 'problema:id,codigo'])->get()->map(fn (Incidencia $i) => [
            $i->codigo, $i->descripcion, $i->organizacion?->razon_social, $i->servicio?->nombre, $i->impacto, $i->urgencia, $i->prioridad,
            $i->responsable?->nombre, $i->estado, $i->ticket?->codigo, $i->problema?->codigo, $i->inicio_en, $i->resuelta_en,
        ]);

        return Csv::descargar('incidencias', ['ID', 'Descripción', 'Organización', 'Servicio', 'Impacto', 'Urgencia', 'Prioridad', 'Responsable', 'Estado', 'Ticket', 'Problema', 'Inicio', 'Resuelta'], $filas);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Incidencia::class);
        $incidencia = new Incidencia(['impacto' => Impacto::Medio, 'urgencia' => Urgencia::Media, 'estado' => EstadoIncidencia::Investigando]);

        return $this->vistaModal('incidencias.form', $this->datosFormulario($request, $incidencia));
    }

    public function store(GuardarIncidenciaRequest $request): JsonResponse|RedirectResponse
    {
        $datos = $this->conPrioridad($request->datosConOrganizacion());
        $incidencia = DB::transaction(fn () => Incidencia::create($datos + ['codigo' => Codigos::siguiente(Incidencia::class, 'INC', 4), 'inicio_en' => now()]));

        return $this->guardado(route('incidencias.index'), ['Incidencia creada', "{$incidencia->codigo} · prioridad {$incidencia->prioridad->label()}."]);
    }

    public function show(Incidencia $incidencia): View
    {
        $this->authorize('view', $incidencia);
        $incidencia->load(['organizacion', 'servicio', 'responsable', 'ticket', 'problema']);

        return $this->vistaModal('incidencias.show', compact('incidencia'));
    }

    public function edit(Request $request, Incidencia $incidencia): View
    {
        $this->authorize('update', $incidencia);

        return $this->vistaModal('incidencias.form', $this->datosFormulario($request, $incidencia));
    }

    public function update(GuardarIncidenciaRequest $request, Incidencia $incidencia): JsonResponse|RedirectResponse
    {
        $incidencia->update($this->conPrioridad($request->datosConOrganizacion(), $incidencia));

        return $this->guardado(route('incidencias.index'), ['Incidencia actualizada', "{$incidencia->codigo}: cambios guardados correctamente."]);
    }

    public function destroy(Incidencia $incidencia): RedirectResponse
    {
        $this->authorize('delete', $incidencia);
        $incidencia->delete();

        return back()->with('ok', ['Registro eliminado', "{$incidencia->codigo} fue eliminada."]);
    }

    /** Prioridad por matriz impacto × urgencia salvo que se indique; fecha de resolución automática. */
    private function conPrioridad(array $datos, ?Incidencia $actual = null): array
    {
        $datos['prioridad'] = filled($datos['prioridad'] ?? null)
            ? $datos['prioridad']
            : Prioridad::desdeMatriz(Impacto::from($datos['impacto']), Urgencia::from($datos['urgencia']))->value;

        $estado = EstadoIncidencia::from($datos['estado']);
        $resuelta = in_array($estado, [EstadoIncidencia::Resuelta, EstadoIncidencia::Cerrada], true);
        $datos['resuelta_en'] = $resuelta ? ($actual?->resuelta_en ?? now()) : null;

        return $datos;
    }

    private function consulta(Request $request): Builder
    {
        return Incidencia::query()
            ->with(['organizacion:id,razon_social', 'servicio:id,nombre', 'responsable:id,nombre'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $texto = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('codigo', 'ilike', $texto)->orWhere('descripcion', 'ilike', $texto)
                    ->orWhereHas('servicio', fn ($s) => $s->where('nombre', 'ilike', $texto)));
            })
            ->when($request->filled('prioridad'), fn ($q) => $q->where('prioridad', $request->string('prioridad')))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->orderByDesc('inicio_en');
    }

    private function datosFormulario(Request $request, Incidencia $incidencia): array
    {
        $usuario = $request->user();
        $gestion = $usuario->can('gestionar', Incidencia::class);

        return [
            'incidencia' => $incidencia,
            'gestion' => $gestion,
            'organizaciones' => $usuario->esAdminSaas() && ! $incidencia->exists ? Organizacion::orderBy('razon_social')->pluck('razon_social', 'id')->all() : [],
            'servicios' => $this->opcionesRelacion(Servicio::query()->orderBy('nombre'), $incidencia),
            'responsables' => $gestion ? $this->opcionesRelacion(Usuario::query()->whereNotNull('organizacion_id')->where('estado', 'activo')->orderBy('nombre'), $incidencia) : collect(),
            'tickets' => $gestion ? $this->opcionesRelacion(
                Ticket::query()->where(fn ($q) => $q->whereDoesntHave('incidencia')->when($incidencia->ticket_id, fn ($w) => $w->orWhere('id', $incidencia->ticket_id)))
                    ->where('tipo', 'incidente')->latest('creado_en')->limit(200),
                $incidencia,
            ) : collect(),
            'problemas' => $gestion ? $this->opcionesRelacion(Problema::query()->orderByDesc('creado_en'), $incidencia) : collect(),
        ];
    }
}
