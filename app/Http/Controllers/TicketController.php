<?php

namespace App\Http\Controllers;

use App\Enums\EstadoTicket;
use App\Enums\Impacto;
use App\Enums\Rol;
use App\Enums\TipoNotificacion;
use App\Enums\TipoTicket;
use App\Enums\Urgencia;
use App\Http\Requests\CambiarEstadoTicketRequest;
use App\Http\Requests\ComentarTicketRequest;
use App\Http\Requests\GuardarTicketRequest;
use App\Models\Activo;
use App\Models\CategoriaTicket;
use App\Models\Notificacion;
use App\Models\Organizacion;
use App\Models\Servicio;
use App\Models\Ticket;
use App\Models\TicketAdjunto;
use App\Models\Usuario;
use App\Services\ActividadTicket;
use App\Services\CargaTecnicos;
use App\Services\FlujoTicket;
use App\Services\NotificacionService;
use App\Services\SlaService;
use App\Services\TicketService;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    public function __construct(private TicketService $tickets, private SlaService $sla) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Ticket::class);

        return view('tickets.index', [
            'tickets' => $this->consulta($request)->paginate(config('servicecloud.paginacion'))->withQueryString(),
            'sla' => $this->sla,
            'flujo' => app(FlujoTicket::class),
            'esSolicitante' => DashboardController::esSolicitante($request->user()),
        ]);
    }

    public function exportar(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Ticket::class);
        $filas = $this->consulta($request)->with(['servicio:id,nombre', 'solicitante:id,nombre'])->get()->map(fn (Ticket $t) => [
            $t->codigo, $t->titulo, $t->organizacion?->razon_social, $t->prioridad, $t->estado, $t->tecnico?->nombre ?? 'Sin asignar',
            $this->sla->estadoSla($t)['texto'], $t->creado_en, $t->tipo, $t->nombreCategoria(), $t->servicio?->nombre, $t->solicitante?->nombre,
            $t->vencimiento_resolucion, $t->resuelto_en,
        ]);

        return Csv::descargar('tickets', ['Código', 'Título', 'Organización', 'Prioridad', 'Estado', 'Técnico', 'SLA', 'Fecha', 'Tipo', 'Categoría', 'Servicio', 'Solicitante', 'Vence', 'Resuelto'], $filas);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Ticket::class);
        // Prellenado opcional por parámetros de la URL (titulo, descripcion, impacto, urgencia, categoria)
        $ticket = new Ticket([
            'tipo' => TipoTicket::Incidente,
            'impacto' => Impacto::tryFrom((string) $request->query('impacto')) ?? Impacto::Medio,
            'urgencia' => Urgencia::tryFrom((string) $request->query('urgencia')) ?? Urgencia::Media,
            'titulo' => $request->query('titulo'), 'descripcion' => $request->query('descripcion'),
        ]);
        if ($request->filled('categoria')) {
            $ticket->categoria_id = CategoriaTicket::where('nombre', (string) $request->query('categoria'))->value('id');
        }

        return $this->vistaModal('tickets.form', $this->datosFormulario($request, $ticket));
    }

    public function store(GuardarTicketRequest $request): JsonResponse|RedirectResponse
    {
        $datos = $request->datosConOrganizacion();
        unset($datos['adjunto']);
        $ticket = $this->tickets->crear($datos, $request->user());
        if ($request->hasFile('adjunto')) {
            $this->tickets->adjuntar($ticket, $request->file('adjunto'), $request->user());
        }

        $tecnico = $ticket->tecnico()->value('nombre');
        if (DashboardController::esSolicitante($request->user())) {
            return $this->guardado(route('tickets.show', $ticket), ['¡Ticket enviado!', $tecnico
                ? "{$ticket->codigo} quedó registrado y {$tecnico} ya lo tiene en su cola."
                : "{$ticket->codigo} quedó registrado. Te avisaremos cuando un técnico lo tome."]);
        }

        return $this->guardado(route('tickets.show', $ticket), ['Ticket creado', "{$ticket->codigo} · prioridad {$ticket->prioridad->label()}".($tecnico ? ", asignado a {$tecnico}" : ', sin técnico').", vence {$ticket->vencimiento_resolucion?->format('d/m/Y H:i')}."]);
    }

    public function show(Request $request, Ticket $ticket, FlujoTicket $flujo): View
    {
        $this->authorize('view', $ticket);
        $ticket->load(['organizacion', 'solicitante', 'tecnico', 'servicio', 'activo', 'categoriaTicket', 'sla.regla.politica', 'adjuntos', 'incidencia', 'problemas']);
        $usuario = $request->user();
        $verInternos = $usuario->can('verInternos', $ticket);
        // Al abrir el ticket, sus avisos dejan de contar como pendientes en la campana
        Notificacion::de($usuario)->where('ticket_id', $ticket->id)->noLeidas()->update(['leida_en' => now()]);

        return view('tickets.show', [
            'ticket' => $ticket,
            'estadoSla' => $this->sla->estadoSla($ticket),
            'flujo' => $flujo,
            'pasos' => $flujo->pasos($ticket),
            // Vista simplificada para quien reportó (no ve datos internos)
            'vistaSolicitante' => ! $verInternos,
            'mensajeSolicitante' => $flujo->mensajeSolicitante($ticket),
            'siguientePaso' => $usuario->can('update', $ticket) ? $flujo->siguientePaso($ticket, $usuario) : null,
            'conversacion' => $ticket->comentarios()->with('autor:id,nombre')
                ->when(! $verInternos, fn ($q) => $q->where('es_interno', false))
                ->orderBy('creado_en')->orderBy('id')->get(),
            'actividad' => app(ActividadTicket::class)->para($ticket, $usuario, conComentarios: false),
        ]);
    }

    public function edit(Request $request, Ticket $ticket): View
    {
        $this->authorize('update', $ticket);

        return $this->vistaModal('tickets.form', $this->datosFormulario($request, $ticket));
    }

    public function update(GuardarTicketRequest $request, Ticket $ticket): JsonResponse|RedirectResponse
    {
        $this->tickets->actualizar($ticket, $request->datosConOrganizacion(), $request->user());

        return $this->guardado(route('tickets.show', $ticket), ['Ticket actualizado', "{$ticket->codigo}: cambios guardados correctamente."]);
    }

    public function cambiarEstado(CambiarEstadoTicketRequest $request, Ticket $ticket): JsonResponse|RedirectResponse
    {
        $nuevo = EstadoTicket::from($request->string('estado'));
        $this->tickets->cambiarEstado($ticket, $nuevo, $request->user(), $request->input('solucion'), $request->input('comentario'));

        $detalle = match ($nuevo) {
            EstadoTicket::EnProgreso => 'Se avisó al usuario que su ticket está en atención.',
            EstadoTicket::EnEspera => 'Se envió tu mensaje al usuario; el SLA queda en pausa hasta que responda.',
            EstadoTicket::Resuelto => 'Se pidió al usuario que confirme la solución.',
            default => "{$ticket->codigo} ahora está en \"{$nuevo->label()}\".",
        };

        return $this->guardado(route('tickets.show', $ticket), ["Ticket {$nuevo->label()}", $detalle]);
    }

    /** GET /tickets/{ticket}/reasignar: modal con la carga de cada técnico y uno recomendado. */
    public function formularioReasignar(Ticket $ticket, CargaTecnicos $cargas): View
    {
        $this->authorize('asignar', $ticket);

        return $this->vistaModal('tickets.reasignar', [
            'ticket' => $ticket->load('tecnico:id,nombre'),
            'tecnicos' => $cargas->de($ticket->organizacion_id),
            'recomendado' => $cargas->elegir($ticket->organizacion_id, $ticket->tecnico_id),
            'maximo' => $cargas->configuracion($ticket->organizacion_id)['maximo'],
        ]);
    }

    /** PATCH /tickets/{ticket}/reasignar */
    public function reasignar(Request $request, Ticket $ticket, CargaTecnicos $cargas): JsonResponse|RedirectResponse
    {
        $this->authorize('asignar', $ticket);
        abort_unless($ticket->estado->esActivo(), 422, 'Solo se reasignan tickets activos.');
        $datos = $request->validate([
            'tecnico_id' => ['required', 'integer', Rule::in($cargas->de($ticket->organizacion_id)->pluck('id'))],
            'motivo' => ['nullable', 'string', 'max:500'],
        ], ['tecnico_id.required' => 'Elige el técnico que atenderá el ticket.', 'tecnico_id.in' => 'Elige un técnico activo de la organización.']);

        $anterior = $ticket->tecnico?->nombre;
        $this->tickets->asignar($ticket, (int) $datos['tecnico_id'], $request->user(), filled($datos['motivo'] ?? null)
            ? 'Reasignación: '.$datos['motivo']
            : ($anterior ? 'Reasignación por supervisor' : 'Asignación por supervisor'));
        $nuevo = $ticket->fresh('tecnico')->tecnico->nombre;

        return $this->guardado(route('tickets.show', $ticket), [$anterior ? 'Ticket reasignado' : 'Ticket asignado', $anterior ? "{$ticket->codigo} pasó de {$anterior} a {$nuevo}. Ambos fueron notificados." : "{$nuevo} atenderá {$ticket->codigo}."]);
    }

    /** POST /tickets/{ticket}/solicitar-reasignacion: el técnico avisa a la gestión que no puede atenderlo. */
    public function solicitarReasignacion(Request $request, Ticket $ticket, NotificacionService $notificaciones): JsonResponse|RedirectResponse
    {
        $this->authorize('solicitarReasignacion', $ticket);
        $datos = $request->validate(['motivo' => ['required', 'string', 'max:500']], ['motivo.required' => 'Cuéntale al supervisor por qué necesitas reasignarlo.']);
        $usuario = $request->user();

        $this->tickets->comentar($ticket, $usuario, 'Solicitó reasignación: '.$datos['motivo'], true);
        foreach ($notificaciones->gestoresDe($ticket->organizacion_id) as $gestor) {
            $notificaciones->notificar($gestor, 'Piden reasignar un ticket', "{$usuario->nombre} pide reasignar {$ticket->codigo}: «".Str::limit($datos['motivo'], 120).'»', TipoNotificacion::Asignacion, $ticket);
        }

        return $this->guardado(route('tickets.show', $ticket), ['Solicitud enviada', 'Avisamos a tu supervisor. Sigues a cargo hasta que lo reasigne.']);
    }

    /** PATCH /tickets/{ticket}/confirmacion: el solicitante confirma la solución o indica que sigue fallando. */
    public function responderSolucion(Request $request, Ticket $ticket): JsonResponse|RedirectResponse
    {
        $this->authorize('responderSolucion', $ticket);
        $datos = $request->validate([
            'decision' => ['required', 'in:confirmar,reabrir'],
            'comentario' => ['nullable', 'required_if:decision,reabrir', 'string', 'max:5000'],
        ], ['comentario.required_if' => 'Cuéntanos qué sigue fallando para que el técnico lo revise.']);

        if ($datos['decision'] === 'confirmar') {
            $this->tickets->cambiarEstado($ticket, EstadoTicket::Cerrado, $request->user(), comentario: $datos['comentario'] ?? null);

            return $this->guardado(route('tickets.show', $ticket), ['¡Gracias por confirmar!', "{$ticket->codigo} quedó cerrado."]);
        }
        $this->tickets->cambiarEstado($ticket, EstadoTicket::EnProgreso, $request->user(), comentario: $datos['comentario']);

        return $this->guardado(route('tickets.show', $ticket), ['Ticket reabierto', 'Avisamos al técnico que el problema continúa.']);
    }

    public function comentar(ComentarTicketRequest $request, Ticket $ticket): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($request, $ticket) {
            if (filled($request->input('contenido'))) {
                $this->tickets->comentar($ticket, $request->user(), $request->string('contenido'), $request->esInterno());
            }
            if ($request->hasFile('adjunto')) {
                $this->tickets->adjuntar($ticket, $request->file('adjunto'), $request->user());
            }
        });

        if ($request->esInterno()) {
            return $this->guardado(route('tickets.show', $ticket), ['Nota interna guardada', 'Solo el equipo de soporte puede verla.']);
        }
        $destinatario = $request->user()->id === $ticket->solicitante_id ? 'el técnico' : 'el usuario';

        return $this->guardado(route('tickets.show', $ticket), ['Mensaje enviado', "Le avisamos a {$destinatario} en {$ticket->codigo}."]);
    }

    public function descargarAdjunto(Ticket $ticket, TicketAdjunto $adjunto): StreamedResponse
    {
        $this->authorize('view', $ticket);
        abort_unless($adjunto->ticket_id === $ticket->id && Storage::disk(config('servicecloud.adjuntos_disco'))->exists($adjunto->ruta_storage), 404);

        return Storage::disk(config('servicecloud.adjuntos_disco'))->download($adjunto->ruta_storage, $adjunto->nombre_original);
    }

    private function consulta(Request $request): Builder
    {
        return Ticket::query()
            ->visiblesPara($request->user())
            ->with(['organizacion:id,razon_social', 'tecnico:id,nombre', 'sla.regla'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $texto = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('codigo', 'ilike', $texto)->orWhere('titulo', 'ilike', $texto)
                    ->orWhereHas('organizacion', fn ($o) => $o->where('razon_social', 'ilike', $texto))
                    ->orWhereHas('tecnico', fn ($o) => $o->where('nombre', 'ilike', $texto)));
            })
            ->when($request->filled('prioridad'), fn ($q) => $q->where('prioridad', $request->string('prioridad')))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->orderByDesc('creado_en');
    }

    private function datosFormulario(Request $request, Ticket $ticket): array
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();
        $esSolicitante = ! $usuario->can('asignar', Ticket::class) && ! $usuario->tieneRol(Rol::Tecnico);

        return [
            'ticket' => $ticket,
            'esSolicitante' => $esSolicitante,
            'puedeAsignar' => $usuario->can('asignar', Ticket::class),
            'puedePriorizar' => $ticket->exists ? $usuario->can('priorizar', $ticket) : $usuario->can('asignar', Ticket::class),
            'organizaciones' => $usuario->esAdminSaas() && ! $ticket->exists ? Organizacion::where('estado', '!=', 'inactiva')->orderBy('razon_social')->pluck('razon_social', 'id')->all() : [],
            'categorias' => $this->opcionesRelacion(CategoriaTicket::query()->orderBy('nombre'), $ticket),
            'servicios' => $this->opcionesRelacion(Servicio::query()->where('estado', '!=', 'retirado')->orderBy('nombre'), $ticket),
            'activos' => $this->opcionesRelacion(Activo::query()->where('estado', '!=', 'retirado')->orderBy('nombre'), $ticket),
            'solicitantes' => $esSolicitante || $ticket->exists ? collect() : $this->opcionesRelacion(Usuario::query()->whereNotNull('organizacion_id')->where('estado', 'activo')->orderBy('nombre'), $ticket),
            'tecnicos' => $this->opcionesRelacion(
                Usuario::query()->where('estado', 'activo')->whereHas('roles', fn ($q) => $q->where('codigo', Rol::Tecnico->value))->orderBy('nombre'),
                $ticket,
            ),
        ];
    }
}
