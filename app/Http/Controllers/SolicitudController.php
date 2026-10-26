<?php

namespace App\Http\Controllers;

use App\Enums\EstadoSolicitud;
use App\Enums\Prioridad;
use App\Enums\Rol;
use App\Enums\TipoNotificacion;
use App\Http\Requests\GuardarSolicitudRequest;
use App\Models\Organizacion;
use App\Models\Solicitud;
use App\Models\Usuario;
use App\Services\NotificacionService;
use App\Support\Codigos;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SolicitudController extends Controller
{
    /** acción => [permiso, estado destino, mensaje] */
    private const ACCIONES = [
        'aprobar' => ['aprobar', EstadoSolicitud::Aprobada, 'aprobada'],
        'rechazar' => ['aprobar', EstadoSolicitud::Rechazada, 'rechazada'],
        'iniciar' => ['atender', EstadoSolicitud::EnProgreso, 'en atención'],
        'completar' => ['atender', EstadoSolicitud::Completada, 'completada'],
        'cancelar' => ['cancelar', EstadoSolicitud::Cancelada, 'cancelada'],
    ];

    public function __construct(private NotificacionService $notificaciones) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Solicitud::class);

        return view('solicitudes.index', [
            'solicitudes' => $this->consulta($request)->paginate(config('servicecloud.paginacion'))->withQueryString(),
        ]);
    }

    public function exportar(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Solicitud::class);
        $filas = $this->consulta($request)->with('aprobador:id,nombre')->get()->map(fn (Solicitud $s) => [
            $s->codigo, $s->titulo, $s->solicitante?->nombre, $s->organizacion?->razon_social, $s->prioridad, $s->estado,
            $s->aprobador?->nombre, $s->creado_en, $s->aprobada_en, $s->completada_en,
        ]);

        return Csv::descargar('solicitudes', ['ID', 'Solicitud', 'Solicitante', 'Organización', 'Prioridad', 'Estado', 'Aprobador', 'Creada', 'Aprobada', 'Completada'], $filas);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Solicitud::class);

        return $this->vistaModal('solicitudes.form', $this->datosFormulario($request, new Solicitud(['prioridad' => Prioridad::Media])));
    }

    public function store(GuardarSolicitudRequest $request): JsonResponse|RedirectResponse
    {
        $datos = $request->datosConOrganizacion();
        $datos['solicitante_id'] ??= $request->user()->id;
        $solicitud = DB::transaction(fn () => Solicitud::create($datos + ['codigo' => Codigos::siguiente(Solicitud::class, 'REQ', 4), 'estado' => EstadoSolicitud::PendienteAprobacion]));

        foreach ($this->notificaciones->gestoresDe($solicitud->organizacion_id) as $gestor) {
            $this->notificaciones->notificar($gestor, 'Solicitud pendiente de aprobación', "{$solicitud->codigo}: {$solicitud->titulo}", TipoNotificacion::Sistema);
        }

        return $this->guardado(route('solicitudes.index'), ['Solicitud registrada', "{$solicitud->codigo} quedó pendiente de aprobación."]);
    }

    public function show(Solicitud $solicitud): View
    {
        $this->authorize('view', $solicitud);
        $solicitud->load(['organizacion', 'solicitante', 'aprobador']);

        return $this->vistaModal('solicitudes.show', compact('solicitud'));
    }

    public function edit(Request $request, Solicitud $solicitud): View
    {
        $this->authorize('update', $solicitud);

        return $this->vistaModal('solicitudes.form', $this->datosFormulario($request, $solicitud));
    }

    public function update(GuardarSolicitudRequest $request, Solicitud $solicitud): JsonResponse|RedirectResponse
    {
        $solicitud->update($request->datosConOrganizacion());

        return $this->guardado(route('solicitudes.index'), ['Solicitud actualizada', "{$solicitud->codigo}: cambios guardados correctamente."]);
    }

    /** PATCH /solicitudes/{solicitud}/{accion}: aprobar, rechazar, iniciar, completar, cancelar. */
    public function transicion(Request $request, Solicitud $solicitud, string $accion): RedirectResponse
    {
        abort_unless(isset(self::ACCIONES[$accion]), 404);
        [$permiso, $destino, $texto] = self::ACCIONES[$accion];
        $this->authorize($permiso, $solicitud);

        $cambios = ['estado' => $destino];
        if (in_array($accion, ['aprobar', 'rechazar'], true)) {
            $cambios += ['aprobador_id' => $request->user()->id, 'aprobada_en' => now()];
        }
        if ($accion === 'completar') {
            $cambios['completada_en'] = now();
        }
        $solicitud->update($cambios);

        if ($solicitud->solicitante_id !== $request->user()->id) {
            $this->notificaciones->notificar($solicitud->solicitante_id, 'Solicitud '.$texto, "{$solicitud->codigo}: {$solicitud->titulo}", TipoNotificacion::Sistema);
        }

        return back()->with($accion === 'rechazar' || $accion === 'cancelar' ? 'aviso' : 'ok', ['Solicitud '.$texto, "{$solicitud->codigo} ahora está en \"{$destino->label()}\"."]);
    }

    public function destroy(Solicitud $solicitud): RedirectResponse
    {
        $this->authorize('delete', $solicitud);
        $solicitud->delete();

        return back()->with('ok', ['Registro eliminado', "{$solicitud->codigo} fue eliminada."]);
    }

    private function consulta(Request $request): Builder
    {
        $usuario = $request->user();

        return Solicitud::query()
            ->with(['organizacion:id,razon_social', 'solicitante:id,nombre'])
            // Solicitante: solo las suyas. Técnico: las que puede atender o ya atendió, y las suyas.
            ->when(! $usuario->esAdminSaas() && ! $usuario->tieneRol(Rol::AdminOrganizacion, Rol::Supervisor), function ($q) use ($usuario) {
                $q->where(function ($w) use ($usuario) {
                    $w->where('solicitante_id', $usuario->id);
                    if ($usuario->tieneRol(Rol::Tecnico)) {
                        $w->orWhereIn('estado', [EstadoSolicitud::Aprobada, EstadoSolicitud::EnProgreso, EstadoSolicitud::Completada]);
                    }
                });
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $texto = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('codigo', 'ilike', $texto)->orWhere('titulo', 'ilike', $texto)
                    ->orWhereHas('solicitante', fn ($s) => $s->where('nombre', 'ilike', $texto)));
            })
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->orderByDesc('creado_en');
    }

    private function datosFormulario(Request $request, Solicitud $solicitud): array
    {
        $usuario = $request->user();
        $esGestion = $usuario->esAdminSaas() || $usuario->tieneRol(Rol::AdminOrganizacion, Rol::Supervisor);

        return [
            'solicitud' => $solicitud,
            'organizaciones' => $usuario->esAdminSaas() && ! $solicitud->exists ? Organizacion::orderBy('razon_social')->pluck('razon_social', 'id')->all() : [],
            'solicitantes' => $esGestion && ! $solicitud->exists
                ? $this->opcionesRelacion(Usuario::query()->whereNotNull('organizacion_id')->where('estado', 'activo')->orderBy('nombre'), $solicitud)
                : collect(),
        ];
    }
}
