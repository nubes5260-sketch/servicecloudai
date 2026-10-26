<?php

namespace App\Console\Commands;

use App\Enums\EstadoTicket;
use App\Enums\TipoNotificacion;
use App\Models\Ticket;
use App\Services\NotificacionService;
use App\Services\SlaService;
use App\Support\Formato;
use Illuminate\Console\Command;

/** Cada 5 minutos: avisa de SLA en riesgo (< 30 min) y marca incumplimientos. */
class RevisarSla extends Command
{
    protected $signature = 'sla:revisar';

    protected $description = 'Notifica tickets con SLA a menos de 30 minutos y marca los vencidos';

    public function handle(SlaService $sla, NotificacionService $notificaciones): int
    {
        $ahora = now();
        $riesgo = 0;
        $vencidos = 0;

        $abiertos = Ticket::sinFiltroOrganizacion()->with('sla.regla')
            ->whereIn('estado', [EstadoTicket::Abierto, EstadoTicket::Asignado, EstadoTicket::EnProgreso])
            ->whereNotNull('vencimiento_resolucion')
            ->where('vencimiento_resolucion', '<=', $ahora->copy()->addMinutes(SlaService::MINUTOS_RIESGO))
            ->get();

        foreach ($abiertos as $ticket) {
            $restantes = $sla->minutosRestantes($ticket);
            $destinatarios = $ticket->tecnico_id
                ? collect([$ticket->tecnico_id])
                : $notificaciones->gestoresDe($ticket->organizacion_id)->pluck('id');

            if ($restantes !== null && $restantes >= 0) {
                $titulo = 'SLA próximo a vencer';
                $mensaje = "{$ticket->codigo} vence en ".($restantes < 60 ? $restantes.' minutos' : Formato::duracion($restantes)).'.';
                $riesgo++;
            } else {
                $titulo = 'SLA vencido';
                $mensaje = "{$ticket->codigo} superó su tiempo de resolución.";
                $destinatarios = $destinatarios->merge($notificaciones->gestoresDe($ticket->organizacion_id)->pluck('id'))->unique();
                if ($ticket->sla && $ticket->sla->resolucion_cumplida === null) {
                    $ticket->sla->update(['resolucion_cumplida' => false, 'calculado_en' => $ahora]);
                }
                $vencidos++;
            }

            foreach ($destinatarios as $usuarioId) {
                if (! $notificaciones->yaNotificado($usuarioId, $ticket->id, $titulo)) {
                    $notificaciones->notificar($usuarioId, $titulo, $mensaje, TipoNotificacion::Sla, $ticket);
                }
            }
        }

        // Respuesta inicial no registrada a tiempo
        Ticket::sinFiltroOrganizacion()->whereNull('primera_respuesta_en')
            ->where('vencimiento_respuesta', '<', $ahora)
            ->whereHas('sla', fn ($q) => $q->whereNull('respuesta_cumplida'))
            ->each(fn (Ticket $t) => $t->sla()->update(['respuesta_cumplida' => false, 'calculado_en' => $ahora]));

        $this->info("SLA en riesgo: {$riesgo} · vencidos: {$vencidos}");

        return self::SUCCESS;
    }
}
