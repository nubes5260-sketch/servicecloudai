<?php

namespace App\Http\Controllers;

use App\Enums\EstadoOrganizacion;
use App\Http\Requests\GuardarOrganizacionRequest;
use App\Models\Organizacion;
use App\Models\PlanSaas;
use App\Services\OrganizacionService;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizacionController extends Controller
{
    public function __construct(private OrganizacionService $servicio) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Organizacion::class);

        return view('organizaciones.index', [
            'organizaciones' => $this->consulta($request)->paginate(config('servicecloud.paginacion'))->withQueryString(),
        ]);
    }

    public function exportar(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Organizacion::class);
        $filas = $this->consulta($request)->get()->map(fn (Organizacion $o) => [
            $o->codigo, $o->razon_social, $o->ruc, $o->sector, $o->usuarios_count, $o->servicios_count, $o->estado, $o->plan?->nombre, $o->email, $o->telefono,
        ]);

        return Csv::descargar('organizaciones', ['ID', 'Organización', 'RUC', 'Sector', 'Usuarios', 'Servicios', 'Estado', 'Plan', 'Correo', 'Teléfono'], $filas);
    }

    public function create(): View
    {
        $this->authorize('create', Organizacion::class);

        return $this->vistaModal('organizaciones.form', ['organizacion' => new Organizacion(['estado' => EstadoOrganizacion::Evaluacion]), 'planes' => $this->planes()]);
    }

    public function store(GuardarOrganizacionRequest $request): JsonResponse|RedirectResponse
    {
        $organizacion = $this->servicio->crear($request->validated());

        return $this->guardado(route('organizaciones.index'), ['Registro creado', "Organizaciones: {$organizacion->razon_social} creada con su configuración y SLA."]);
    }

    public function show(Organizacion $organizacion): View
    {
        $this->authorize('view', $organizacion);
        $organizacion->loadCount(['usuarios', 'servicios', 'activos', 'contratos', 'tickets'])->load('plan');

        return $this->vistaModal('organizaciones.show', compact('organizacion'));
    }

    public function edit(Organizacion $organizacion): View
    {
        $this->authorize('update', $organizacion);

        return $this->vistaModal('organizaciones.form', ['organizacion' => $organizacion, 'planes' => $this->planes()]);
    }

    public function update(GuardarOrganizacionRequest $request, Organizacion $organizacion): JsonResponse|RedirectResponse
    {
        $organizacion->update($request->validated());

        return $this->guardado(route('organizaciones.index'), ['Registro actualizado', 'Organizaciones: cambios guardados correctamente.']);
    }

    public function destroy(Organizacion $organizacion): RedirectResponse
    {
        $this->authorize('delete', $organizacion);

        if ($this->servicio->eliminarODesactivar($organizacion)) {
            return back()->with('ok', ['Registro eliminado', "{$organizacion->codigo} fue eliminado."]);
        }

        return back()->with('aviso', ['Organización desactivada', "{$organizacion->razon_social} tiene registros asociados; se marcó como Inactiva."]);
    }

    private function consulta(Request $request): Builder
    {
        $usuario = $request->user();

        return Organizacion::query()
            ->with('plan')
            ->withCount([
                'usuarios' => fn ($q) => $q->withoutGlobalScopes(),
                'servicios' => fn ($q) => $q->withoutGlobalScopes(),
            ])
            ->when(! $usuario->esAdminSaas(), fn ($q) => $q->whereKey($usuario->organizacion_id))
            ->when($request->filled('q'), function ($q) use ($request) {
                $texto = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('razon_social', 'ilike', $texto)->orWhere('codigo', 'ilike', $texto)
                    ->orWhere('sector', 'ilike', $texto)->orWhere('ruc', 'ilike', $texto));
            })
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->orderBy('codigo');
    }

    private function planes(): array
    {
        return PlanSaas::where('activo', true)->orderBy('id')->pluck('nombre', 'id')->all();
    }
}
