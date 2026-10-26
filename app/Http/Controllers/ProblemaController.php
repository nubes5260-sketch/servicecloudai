<?php

namespace App\Http\Controllers;

use App\Enums\EstadoProblema;
use App\Http\Requests\GuardarProblemaRequest;
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

class ProblemaController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Problema::class);

        return view('problemas.index', [
            'problemas' => $this->consulta($request)->paginate(config('servicecloud.paginacion'))->withQueryString(),
        ]);
    }

    public function exportar(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Problema::class);
        $filas = $this->consulta($request)->with('tickets:id,codigo')->get()->map(fn (Problema $p) => [
            $p->codigo, $p->titulo, $p->organizacion?->razon_social, $p->servicio?->nombre, $p->causa_raiz, $p->solucion_temporal,
            $p->responsable?->nombre, $p->estado, $p->tickets->pluck('codigo')->join(' / '), $p->creado_en, $p->cerrado_en,
        ]);

        return Csv::descargar('problemas', ['ID', 'Problema', 'Organización', 'Servicio', 'Causa raíz', 'Workaround', 'Responsable', 'Estado', 'Tickets', 'Creado', 'Cerrado'], $filas);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Problema::class);

        return $this->vistaModal('problemas.form', $this->datosFormulario($request, new Problema(['estado' => EstadoProblema::Abierto])));
    }

    public function store(GuardarProblemaRequest $request): JsonResponse|RedirectResponse
    {
        $datos = $this->conCierre($request->datosConOrganizacion());
        $tickets = $datos['tickets'] ?? [];
        unset($datos['tickets']);

        $problema = DB::transaction(function () use ($datos, $tickets) {
            $problema = Problema::create($datos + ['codigo' => Codigos::siguiente(Problema::class, 'PRB', 3)]);
            $problema->tickets()->sync(array_fill_keys($tickets, ['organizacion_id' => $problema->organizacion_id]));

            return $problema;
        });

        return $this->guardado(route('problemas.index'), ['Registro creado', "Problemas: {$problema->codigo} fue creado."]);
    }

    public function show(Problema $problema): View
    {
        $this->authorize('view', $problema);
        $problema->load(['organizacion', 'servicio', 'responsable', 'tickets:id,codigo,titulo,estado', 'incidencias:id,problema_id,codigo']);

        return $this->vistaModal('problemas.show', compact('problema'));
    }

    public function edit(Request $request, Problema $problema): View
    {
        $this->authorize('update', $problema);
        $problema->load('tickets:id');

        return $this->vistaModal('problemas.form', $this->datosFormulario($request, $problema));
    }

    public function update(GuardarProblemaRequest $request, Problema $problema): JsonResponse|RedirectResponse
    {
        $datos = $this->conCierre($request->datosConOrganizacion(), $problema);
        $tickets = $datos['tickets'] ?? null;
        unset($datos['tickets']);

        DB::transaction(function () use ($problema, $datos, $tickets) {
            $problema->update($datos);
            if ($tickets !== null || request()->has('tickets_enviado')) {
                $problema->tickets()->sync(array_fill_keys($tickets ?? [], ['organizacion_id' => $problema->organizacion_id]));
            }
        });

        return $this->guardado(route('problemas.index'), ['Registro actualizado', 'Problemas: cambios guardados correctamente.']);
    }

    public function destroy(Problema $problema): RedirectResponse
    {
        $this->authorize('delete', $problema);
        $problema->delete(); // incidencias.problema_id queda en NULL; problemas_tickets en cascada

        return back()->with('ok', ['Registro eliminado', "{$problema->codigo} fue eliminado."]);
    }

    private function conCierre(array $datos, ?Problema $actual = null): array
    {
        $cerrado = in_array(EstadoProblema::from($datos['estado']), [EstadoProblema::Resuelto, EstadoProblema::Cerrado], true);
        $datos['cerrado_en'] = $cerrado ? ($actual?->cerrado_en ?? now()) : null;

        return $datos;
    }

    private function consulta(Request $request): Builder
    {
        return Problema::query()
            ->with(['organizacion:id,razon_social', 'servicio:id,nombre', 'responsable:id,nombre'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $texto = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('codigo', 'ilike', $texto)->orWhere('titulo', 'ilike', $texto)->orWhere('causa_raiz', 'ilike', $texto));
            })
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->orderByDesc('creado_en');
    }

    private function datosFormulario(Request $request, Problema $problema): array
    {
        $usuario = $request->user();
        $gestion = $usuario->can('gestionar', Problema::class);

        return [
            'problema' => $problema,
            'gestion' => $gestion,
            'organizaciones' => $usuario->esAdminSaas() && ! $problema->exists ? Organizacion::orderBy('razon_social')->pluck('razon_social', 'id')->all() : [],
            'servicios' => $this->opcionesRelacion(Servicio::query()->orderBy('nombre'), $problema),
            'responsables' => $gestion ? $this->opcionesRelacion(Usuario::query()->whereNotNull('organizacion_id')->where('estado', 'activo')->orderBy('nombre'), $problema) : collect(),
            'tickets' => $this->opcionesRelacion(Ticket::query()->latest('creado_en')->limit(200), $problema),
        ];
    }
}
