<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Centro de notificaciones (renderNotifications): cada usuario solo ve las suyas. */
class NotificacionController extends Controller
{
    public function index(Request $request): View
    {
        $notificaciones = Notificacion::de($request->user())
            ->with('ticket:id,codigo')
            ->when($request->query('filtro') === 'no_leidas', fn ($q) => $q->noLeidas())
            ->latest('creado_en')->latest('id')
            ->paginate(20)->withQueryString();

        return view('notificaciones.index', compact('notificaciones'));
    }

    /** Abre una notificación: la marca como leída y lleva al ticket relacionado si lo hay. */
    public function abrir(Request $request, Notificacion $notificacion): RedirectResponse
    {
        abort_unless($notificacion->usuario_id === $request->user()->id, 404);
        if (! $notificacion->leida_en) {
            $notificacion->update(['leida_en' => now()]);
        }
        $ticket = $notificacion->ticket;

        return $ticket && $request->user()->can('view', $ticket)
            ? redirect()->route('tickets.show', $ticket)
            : redirect()->route('notificaciones.index');
    }

    /**
     * GET /notificaciones/resumen?desde={id}: lo consulta la campana cada pocos segundos.
     * Devuelve el contador, el HTML del desplegable y las nuevas (id > desde) para mostrarlas como aviso.
     */
    public function resumen(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $noLeidas = Notificacion::de($usuario)->noLeidas()->count();
        $ultimas = Notificacion::de($usuario)->latest('creado_en')->latest('id')->limit(6)->get();
        $desde = (int) $request->query('desde');
        $nuevas = $desde > 0
            ? Notificacion::de($usuario)->with('ticket:id,codigo')->noLeidas()->where('id', '>', $desde)->orderBy('id')->limit(3)->get()
            : collect();

        return response()->json([
            'no_leidas' => $noLeidas,
            'ultima' => (int) Notificacion::de($usuario)->max('id'),
            'html' => view('partials.notificaciones-dropdown', ['notificacionesHeader' => $ultimas, 'notificacionesNoLeidas' => $noLeidas])->render(),
            'nuevas' => $nuevas->map(fn (Notificacion $n) => [
                'id' => $n->id, 'titulo' => $n->titulo, 'mensaje' => $n->mensaje,
                'icono' => $n->apariencia()[0], 'url' => route('notificaciones.abrir', $n), 'ticket' => $n->ticket?->codigo,
            ])->values(),
        ]);
    }

    public function marcarTodas(Request $request): RedirectResponse
    {
        $cantidad = Notificacion::de($request->user())->noLeidas()->update(['leida_en' => now()]);

        return back()->with('ok', ['Notificaciones actualizadas', $cantidad > 0 ? "{$cantidad} marcadas como leídas." : 'Todas fueron marcadas como leídas.']);
    }
}
