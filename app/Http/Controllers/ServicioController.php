<?php

namespace App\Http\Controllers;

use App\Enums\EstadoServicio;
use App\Http\Requests\GuardarServicioRequest;
use App\Models\Activo;
use App\Models\CategoriaServicio;
use App\Models\Contrato;
use App\Models\Organizacion;
use App\Models\PoliticaSla;
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

class ServicioController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Servicio::class);

        return view('servicios.index', [
            'servicios' => $this->consulta($request)->paginate(config('servicecloud.paginacion'))->withQueryString(),
        ]);
    }

    public function exportar(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Servicio::class);
        $filas = $this->consulta($request)->with('activos:id,nombre')->get()->map(fn (Servicio $s) => [
            $s->codigo, $s->nombre, $s->organizacion?->razon_social, $s->responsable?->nombre, $this->disponibilidad($s), $s->estado,
            $s->politicaSla?->nombre, $s->contrato?->codigo, $s->categoria, $s->activos->pluck('nombre')->join(' / '),
        ]);

        return Csv::descargar('servicios', ['ID', 'Servicio', 'Organización', 'Owner', 'Disponibilidad', 'Estado', 'SLA', 'Contrato', 'Categoría', 'Activos'], $filas);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Servicio::class);

        return $this->vistaModal('servicios.form', $this->datosFormulario($request, new Servicio(['estado' => EstadoServicio::Operativo])));
    }

    public function store(GuardarServicioRequest $request): JsonResponse|RedirectResponse
    {
        $datos = $request->datosConOrganizacion();
        $activos = $datos['activos'] ?? [];
        unset($datos['activos']);

        $servicio = DB::transaction(function () use ($datos, $activos) {
            $servicio = Servicio::create($this->conCategoria($datos, (int) $datos['organizacion_id']) + ['codigo' => Codigos::siguiente(Servicio::class, 'SRV', 2)]);
            $servicio->activos()->attach(array_fill_keys($activos, ['organizacion_id' => $servicio->organizacion_id]));

            return $servicio;
        });

        return $this->guardado(route('servicios.index'), ['Registro creado', "Servicios: {$servicio->nombre} fue creado."]);
    }

    public function show(Servicio $servicio): View
    {
        $this->authorize('view', $servicio);
        $servicio->load(['organizacion', 'responsable', 'contrato', 'politicaSla', 'activos'])->loadCount(['tickets', 'incidencias']);

        return $this->vistaModal('servicios.show', ['servicio' => $servicio, 'disponibilidad' => $this->disponibilidad($servicio)]);
    }

    public function edit(Request $request, Servicio $servicio): View
    {
        $this->authorize('update', $servicio);
        $servicio->load('activos:id');

        return $this->vistaModal('servicios.form', $this->datosFormulario($request, $servicio));
    }

    public function update(GuardarServicioRequest $request, Servicio $servicio): JsonResponse|RedirectResponse
    {
        $datos = $request->datosConOrganizacion();
        $activos = $datos['activos'] ?? [];
        unset($datos['activos']);

        DB::transaction(function () use ($servicio, $datos, $activos) {
            $servicio->update($this->conCategoria($datos, $servicio->organizacion_id));
            $servicio->activos()->sync(array_fill_keys($activos, ['organizacion_id' => $servicio->organizacion_id]));
        });

        return $this->guardado(route('servicios.index'), ['Registro actualizado', 'Servicios: cambios guardados correctamente.']);
    }

    public function destroy(Servicio $servicio): RedirectResponse
    {
        $this->authorize('delete', $servicio);

        if ($servicio->tickets()->exists() || $servicio->incidencias()->exists()) {
            $servicio->update(['estado' => EstadoServicio::Retirado]);

            return back()->with('aviso', ['Servicio retirado', "{$servicio->nombre} tiene tickets o incidencias; se marcó como Retirado."]);
        }
        $servicio->delete();

        return back()->with('ok', ['Registro eliminado', "{$servicio->codigo} fue eliminado."]);
    }

    private function consulta(Request $request): Builder
    {
        return Servicio::query()
            ->with(['organizacion:id,razon_social', 'responsable:id,nombre', 'politicaSla:id,nombre', 'contrato:id,codigo', 'categoriaCatalogo:id,nombre'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $texto = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('nombre', 'ilike', $texto)->orWhere('codigo', 'ilike', $texto)->orWhereRelation('categoriaCatalogo', 'nombre', 'ilike', $texto)
                    ->orWhereHas('organizacion', fn ($o) => $o->where('razon_social', 'ilike', $texto)));
            })
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->orderBy('codigo');
    }

    private function disponibilidad(Servicio $servicio): string
    {
        return $servicio->disponibilidad_objetivo === null ? '—' : rtrim(rtrim((string) $servicio->disponibilidad_objetivo, '0'), '.').'%';
    }

    private function datosFormulario(Request $request, Servicio $servicio): array
    {
        return [
            'servicio' => $servicio,
            'organizaciones' => $request->user()->esAdminSaas() && ! $servicio->exists ? Organizacion::orderBy('razon_social')->pluck('razon_social', 'id')->all() : [],
            'responsables' => $this->opcionesRelacion(Usuario::query()->whereNotNull('organizacion_id')->where('estado', 'activo')->orderBy('nombre'), $servicio),
            'contratos' => $this->opcionesRelacion(Contrato::query()->orderBy('codigo'), $servicio),
            'politicas' => $this->opcionesRelacion(PoliticaSla::query()->whereNotNull('organizacion_id')->where('activo', true)->orderBy('nombre'), $servicio),
            'activos' => $this->opcionesRelacion(Activo::query()->orderBy('nombre'), $servicio),
            'categorias' => CategoriaServicio::query()->orderBy('nombre')->pluck('nombre')->unique()->values(),
        ];
    }

    /** El formulario envía el nombre de la categoría; se guarda su id del catálogo (se crea si es nueva). */
    private function conCategoria(array $datos, int $organizacionId): array
    {
        if (array_key_exists('categoria', $datos)) {
            $datos['categoria_id'] = CategoriaServicio::idPara($organizacionId, $datos['categoria']);
            unset($datos['categoria']);
        }

        return $datos;
    }
}
