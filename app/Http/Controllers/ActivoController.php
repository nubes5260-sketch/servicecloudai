<?php

namespace App\Http\Controllers;

use App\Enums\EstadoActivo;
use App\Enums\TipoActivo;
use App\Http\Requests\GuardarActivoRequest;
use App\Models\Activo;
use App\Models\Organizacion;
use App\Models\Servicio;
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

class ActivoController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Activo::class);

        return view('activos.index', [
            'activos' => $this->consulta($request)->paginate(config('servicecloud.paginacion'))->withQueryString(),
        ]);
    }

    public function exportar(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Activo::class);
        $filas = $this->consulta($request)->with('servicios:id,nombre')->get()->map(fn (Activo $a) => [
            $a->codigo, $a->nombre, $a->tipo, $a->organizacion?->razon_social, $a->ip, $a->hostname, $a->estado,
            $a->responsable?->nombre, $a->servicios->pluck('nombre')->join(' / '), $a->fabricante, $a->modelo, $a->proveedor_cloud, $a->region_cloud,
        ]);

        return Csv::descargar('activos', ['ID', 'Nombre del activo', 'Tipo', 'Organización', 'IP', 'Hostname', 'Estado', 'Responsable', 'Servicios', 'Fabricante', 'Modelo', 'Proveedor cloud', 'Región'], $filas);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Activo::class);

        return $this->vistaModal('activos.form', $this->datosFormulario($request, new Activo(['tipo' => TipoActivo::Servidor, 'estado' => EstadoActivo::Operativo])));
    }

    public function store(GuardarActivoRequest $request): JsonResponse|RedirectResponse
    {
        $datos = $request->datosConOrganizacion();
        $servicios = $datos['servicios'] ?? [];
        unset($datos['servicios']);

        $activo = DB::transaction(function () use ($datos, $servicios) {
            $activo = Activo::create($datos + [
                'codigo' => Codigos::siguiente(Activo::class, 'AST', 4),
            ]);
            $activo->servicios()->attach(array_fill_keys($servicios, ['organizacion_id' => $activo->organizacion_id]));

            return $activo;
        });

        return $this->guardado(route('activos.index'), ['Registro creado', "Activos: {$activo->codigo} · {$activo->nombre} fue creado."]);
    }

    public function show(Activo $activo): View
    {
        $this->authorize('view', $activo);
        $activo->load(['organizacion', 'responsable', 'servicios'])->loadCount('tickets');

        return $this->vistaModal('activos.show', compact('activo'));
    }

    public function edit(Request $request, Activo $activo): View
    {
        $this->authorize('update', $activo);
        $activo->load('servicios:id');

        return $this->vistaModal('activos.form', $this->datosFormulario($request, $activo));
    }

    public function update(GuardarActivoRequest $request, Activo $activo): JsonResponse|RedirectResponse
    {
        $datos = $request->datosConOrganizacion();
        $servicios = $datos['servicios'] ?? [];
        unset($datos['servicios']);

        DB::transaction(function () use ($activo, $datos, $servicios) {
            $activo->update($datos);
            $activo->servicios()->sync(array_fill_keys($servicios, ['organizacion_id' => $activo->organizacion_id]));
        });

        return $this->guardado(route('activos.index'), ['Registro actualizado', 'Activos: cambios guardados correctamente.']);
    }

    public function destroy(Activo $activo): RedirectResponse
    {
        $this->authorize('delete', $activo);
        $activo->delete(); // tickets.activo_id queda en NULL; servicios_activos en cascada

        return back()->with('ok', ['Registro eliminado', "{$activo->codigo} fue eliminado."]);
    }

    private function consulta(Request $request): Builder
    {
        return Activo::query()
            ->with(['organizacion:id,razon_social', 'responsable:id,nombre'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $texto = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('nombre', 'ilike', $texto)->orWhere('codigo', 'ilike', $texto)
                    ->orWhere('ip', 'ilike', $texto)->orWhere('hostname', 'ilike', $texto)
                    ->orWhereHas('organizacion', fn ($o) => $o->where('razon_social', 'ilike', $texto)));
            })
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->string('tipo')))
            ->orderBy('codigo');
    }

    private function datosFormulario(Request $request, Activo $activo): array
    {
        return [
            'activo' => $activo,
            'organizaciones' => $request->user()->esAdminSaas() && ! $activo->exists ? Organizacion::orderBy('razon_social')->pluck('razon_social', 'id')->all() : [],
            'responsables' => $this->opcionesRelacion(Usuario::query()->whereNotNull('organizacion_id')->where('estado', 'activo')->orderBy('nombre'), $activo),
            'servicios' => $this->opcionesRelacion(Servicio::query()->orderBy('nombre'), $activo),
        ];
    }
}
