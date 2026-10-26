<?php

namespace App\Http\Controllers;

use App\Enums\EstadoTicket;
use App\Enums\Rol;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Services\DashboardService;
use App\Services\FlujoTicket;
use App\Services\SlaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Segundos que se reutilizan las métricas (fase 6: 60 s por alcance). */
    public const CACHE_SEGUNDOS = 60;

    public function __invoke(Request $request, DashboardService $dashboard, FlujoTicket $flujo, SlaService $sla): View
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();

        // Solicitante: su propio portal (qué pasa con mis tickets), sin métricas operativas
        if (self::esSolicitante($usuario)) {
            $tickets = Ticket::query()->where('solicitante_id', $usuario->id)
                ->with('tecnico:id,nombre')
                ->orderByRaw("case estado when 'en_espera' then 1 when 'resuelto' then 2 else 3 end")
                ->latest('actualizado_en')
                ->limit(40)->get();
            $activos = $tickets->filter(fn (Ticket $t) => ! in_array($t->estado, [EstadoTicket::Cerrado, EstadoTicket::Cancelado], true));

            return view('dashboard-solicitante', [
                'activos' => $activos->values(),
                'cerrados' => $tickets->filter(fn (Ticket $t) => $t->estado === EstadoTicket::Cerrado)->sortByDesc('cerrado_en')->take(5)->values(),
                'conteo' => [
                    'curso' => $activos->whereIn('estado', [EstadoTicket::Abierto, EstadoTicket::Asignado, EstadoTicket::EnProgreso])->count(),
                    'espera' => $activos->where('estado', EstadoTicket::EnEspera)->count(),
                    'confirmar' => $activos->where('estado', EstadoTicket::Resuelto)->count(),
                ],
                'flujo' => $flujo,
            ]);
        }

        $datos = Cache::remember(self::claveCache($usuario), self::CACHE_SEGUNDOS, fn () => $dashboard->datos($usuario));

        // Técnico: su cola de trabajo, siempre al día (no se cachea)
        $cola = null;
        if ($usuario->tieneRol(Rol::Tecnico) && ! $usuario->tieneRol(Rol::AdminOrganizacion, Rol::Supervisor)) {
            $cola = Ticket::query()->where('tecnico_id', $usuario->id)->abiertos()
                ->with(['solicitante:id,nombre', 'sla.regla'])
                ->orderByRaw("case estado when 'asignado' then 1 when 'en_progreso' then 2 when 'abierto' then 3 else 4 end")
                ->orderByRaw('vencimiento_resolucion asc nulls last')
                ->get()
                ->map(fn (Ticket $t) => ['ticket' => $t, 'sla' => $sla->estadoSla($t), 'paso' => $flujo->siguientePaso($t, $usuario)]);
        }

        return view('dashboard', $datos + ['cola' => $cola]);
    }

    /** Solo crea y sigue sus propios tickets (no atiende ni gestiona). */
    public static function esSolicitante(Usuario $usuario): bool
    {
        return ! $usuario->esAdminSaas() && ! $usuario->tieneRol(Rol::AdminOrganizacion, Rol::Supervisor, Rol::Tecnico);
    }

    /** Alcance de las métricas: plataforma, organización (gestión) o usuario (técnico/solicitante). */
    public static function claveCache(Usuario $usuario): string
    {
        return match (true) {
            $usuario->esAdminSaas() => 'dashboard:global',
            $usuario->tieneRol(Rol::AdminOrganizacion, Rol::Supervisor) => 'dashboard:org:'.$usuario->organizacion_id,
            default => 'dashboard:usuario:'.$usuario->id,
        };
    }
}
