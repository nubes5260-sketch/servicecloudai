<?php

namespace App\Http\Controllers;

use App\Enums\EstadoTicket;
use App\Models\Ticket;
use App\Services\AsignacionService;
use App\Services\CargaTecnicos;
use App\Services\SlaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AsignacionController extends Controller
{
    public function __construct(private AsignacionService $asignaciones, private SlaService $sla, private CargaTecnicos $cargas) {}

    public function index(Request $request): View
    {
        $tickets = Ticket::visiblesPara($request->user())
            ->with(['tecnico:id,nombre', 'sla.regla'])
            ->where(fn ($q) => $q->whereIn('estado', [EstadoTicket::Abierto, EstadoTicket::Asignado, EstadoTicket::EnProgreso, EstadoTicket::EnEspera])
                ->orWhere(fn ($r) => $r->where('estado', EstadoTicket::Resuelto)->where('resuelto_en', '>=', now()->subDays(7))))
            ->orderByRaw("case prioridad when 'critica' then 1 when 'alta' then 2 when 'media' then 3 else 4 end")
            ->orderBy('creado_en')
            ->get();

        return view('asignaciones.index', [
            'columnas' => collect(AsignacionService::COLUMNAS)->map(fn ($titulo, $clave) => [
                'titulo' => $titulo,
                'tickets' => $tickets->filter(fn (Ticket $t) => AsignacionService::columnaDe($t) === $clave)->values(),
            ]),
            'sla' => $this->sla,
            // Carga del equipo para quien asigna (de su organización)
            'equipo' => $request->user()->can('asignar', Ticket::class) && $request->user()->organizacion_id
                ? $this->cargas->de($request->user()->organizacion_id) : null,
            'configuracionCarga' => $request->user()->organizacion_id ? $this->cargas->configuracion($request->user()->organizacion_id) : null,
        ]);
    }

    /** PATCH /asignaciones/{ticket} desde el drag & drop del kanban. */
    public function mover(Request $request, Ticket $ticket): JsonResponse|RedirectResponse
    {
        $this->authorize('mover', $ticket);
        $datos = $request->validate([
            'columna' => ['required', Rule::in(array_keys(AsignacionService::COLUMNAS))],
            'solucion' => ['nullable', 'required_if:columna,resuelto', 'string', 'max:5000'],
        ], ['solucion.required_if' => 'Describe la solución para mover el ticket a Resuelto.']);

        $this->asignaciones->mover($ticket, $datos['columna'], $request->user(), $datos['solucion'] ?? null);

        return $this->guardado(route('asignaciones.index'), ['Asignación actualizada', "{$ticket->codigo} movido a ".AsignacionService::COLUMNAS[$datos['columna']].'.']);
    }

    public function autoasignar(Request $request): RedirectResponse
    {
        $this->authorize('asignar', Ticket::class);
        $asignados = $this->asignaciones->autoasignar($request->user());

        return back()->with('ok', $asignados->isEmpty()
            ? ['Autoasignación completada', 'No hay tickets sin asignar.']
            : ['Autoasignación completada', $asignados->count().' '.($asignados->count() === 1 ? 'ticket fue asignado' : 'tickets fueron asignados').' por carga: '.$asignados->map(fn ($a) => "{$a['ticket']} → {$a['tecnico']}")->join(', ').'.']);
    }
}
