<?php

namespace App\Services;

use App\Enums\TipoNotificacion;
use App\Models\Notificacion;
use App\Models\PreferenciaNotificacion;
use App\Models\Ticket;
use App\Models\Usuario;
use Illuminate\Support\Collection;

/** Notificaciones in-app (respetan preferencias_notificaciones.in_app_activo). */
class NotificacionService
{
    public function notificar(Usuario|int|null $usuario, string $titulo, string $mensaje, TipoNotificacion $tipo, ?Ticket $ticket = null): ?Notificacion
    {
        if ($usuario === null) {
            return null;
        }
        $usuario = $usuario instanceof Usuario ? $usuario : Usuario::sinFiltroOrganizacion()->find($usuario);
        if (! $usuario || ! $usuario->estaActivo()) {
            return null;
        }
        $preferencias = PreferenciaNotificacion::find($usuario->id);
        if ($preferencias && ! $preferencias->in_app_activo) {
            return null;
        }

        return Notificacion::create([
            'organizacion_id' => $usuario->organizacion_id ?? $ticket?->organizacion_id,
            'usuario_id' => $usuario->id,
            'ticket_id' => $ticket?->id,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'tipo' => $tipo,
            'canal' => 'in_app',
            'enviada_en' => now(),
        ]);
    }

    /** Supervisores y administradores de la organización (para alertas de SLA). */
    public function gestoresDe(int $organizacionId): Collection
    {
        return Usuario::sinFiltroOrganizacion()
            ->where('organizacion_id', $organizacionId)
            ->where('estado', 'activo')
            ->whereHas('roles', fn ($q) => $q->whereIn('codigo', ['SUPERVISOR', 'ADMIN_ORGANIZACION']))
            ->get();
    }

    /** ¿Ya se envió esta alerta para el ticket a ese usuario recientemente? (evita duplicados del programador) */
    public function yaNotificado(int $usuarioId, int $ticketId, string $titulo, int $horas = 6): bool
    {
        return Notificacion::sinFiltroOrganizacion()
            ->where('usuario_id', $usuarioId)->where('ticket_id', $ticketId)->where('titulo', $titulo)
            ->where('creado_en', '>=', now()->subHours($horas))
            ->exists();
    }
}
