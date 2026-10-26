<?php

namespace App\Policies;

use App\Enums\EstadoTicket;
use App\Enums\Rol;
use App\Models\Ticket;
use App\Models\Usuario;

/**
 * Regla 03: ADMIN_SAAS todo; ADMIN_ORGANIZACION y SUPERVISOR todo en su organización;
 * TECNICO asignados + crear; SOLICITANTE crear, ver los suyos y comentar.
 * (El filtro de organización lo aplica el scope: otro tenant recibe 404.)
 */
class TicketPolicy
{
    private function esGestion(Usuario $usuario): bool
    {
        return $usuario->esAdminSaas() || $usuario->tieneRol(Rol::AdminOrganizacion, Rol::Supervisor);
    }

    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->puedeVerModulo('tickets');
    }

    public function view(Usuario $usuario, Ticket $ticket): bool
    {
        return $this->esGestion($usuario)
            || $ticket->tecnico_id === $usuario->id
            || $ticket->solicitante_id === $usuario->id;
    }

    public function create(Usuario $usuario): bool
    {
        return $this->viewAny($usuario);
    }

    /** Editar datos del ticket y cambiar su estado. */
    public function update(Usuario $usuario, Ticket $ticket): bool
    {
        return $this->esGestion($usuario)
            || ($usuario->tieneRol(Rol::Tecnico) && $ticket->tecnico_id === $usuario->id);
    }

    /** Asignar o reasignar técnico. */
    public function asignar(Usuario $usuario, ?Ticket $ticket = null): bool
    {
        return $this->esGestion($usuario);
    }

    /** Cambiar la prioridad manualmente (fuera de la matriz impacto × urgencia). */
    public function priorizar(Usuario $usuario, Ticket $ticket): bool
    {
        return $this->esGestion($usuario);
    }

    /** Mover en el kanban: gestión cualquiera; técnico los suyos o tomar uno sin asignar. */
    public function mover(Usuario $usuario, Ticket $ticket): bool
    {
        return $this->esGestion($usuario)
            || ($usuario->tieneRol(Rol::Tecnico) && ($ticket->tecnico_id === $usuario->id || $ticket->tecnico_id === null));
    }

    /** El técnico a cargo pide a la gestión que lo reasigne (p. ej. por sobrecarga). */
    public function solicitarReasignacion(Usuario $usuario, Ticket $ticket): bool
    {
        return $usuario->tieneRol(Rol::Tecnico) && $ticket->tecnico_id === $usuario->id && $ticket->estado->esActivo();
    }

    /** Quien reportó el ticket confirma la solución (cierra) o indica que sigue fallando (reabre). */
    public function responderSolucion(Usuario $usuario, Ticket $ticket): bool
    {
        return $ticket->solicitante_id === $usuario->id && $ticket->estado === EstadoTicket::Resuelto;
    }

    public function comentar(Usuario $usuario, Ticket $ticket): bool
    {
        return $this->view($usuario, $ticket);
    }

    /** Notas internas y ver notas internas: nunca el solicitante. */
    public function verInternos(Usuario $usuario, ?Ticket $ticket = null): bool
    {
        return $this->esGestion($usuario) || $usuario->tieneRol(Rol::Tecnico);
    }
}
