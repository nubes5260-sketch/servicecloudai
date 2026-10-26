<?php

namespace App\Policies;

use App\Enums\EstadoSolicitud;
use App\Enums\Rol;
use App\Models\Solicitud;
use App\Models\Usuario;

/**
 * Regla 03: ADMIN_SAAS todo; ADMIN_ORGANIZACION y SUPERVISOR aprueban; TECNICO atiende;
 * SOLICITANTE crea y ve las suyas.
 */
class SolicitudPolicy
{
    private function esAprobador(Usuario $usuario): bool
    {
        return $usuario->esAdminSaas() || $usuario->tieneRol(Rol::AdminOrganizacion, Rol::Supervisor);
    }

    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->puedeVerModulo('solicitudes');
    }

    public function view(Usuario $usuario, Solicitud $solicitud): bool
    {
        return $this->esAprobador($usuario) || $usuario->tieneRol(Rol::Tecnico) || $solicitud->solicitante_id === $usuario->id;
    }

    public function create(Usuario $usuario): bool
    {
        return $this->esAprobador($usuario) || $usuario->tieneRol(Rol::Solicitante);
    }

    /** Editar: aprobadores siempre; el solicitante mientras esté pendiente. */
    public function update(Usuario $usuario, Solicitud $solicitud): bool
    {
        return $this->esAprobador($usuario)
            || ($solicitud->solicitante_id === $usuario->id && $solicitud->estado === EstadoSolicitud::PendienteAprobacion);
    }

    public function aprobar(Usuario $usuario, Solicitud $solicitud): bool
    {
        return $this->esAprobador($usuario) && $solicitud->estado === EstadoSolicitud::PendienteAprobacion;
    }

    public function atender(Usuario $usuario, Solicitud $solicitud): bool
    {
        return ($usuario->esAdminSaas() || $usuario->tieneRol(Rol::AdminOrganizacion, Rol::Tecnico))
            && in_array($solicitud->estado, [EstadoSolicitud::Aprobada, EstadoSolicitud::EnProgreso], true);
    }

    public function cancelar(Usuario $usuario, Solicitud $solicitud): bool
    {
        return in_array($solicitud->estado, [EstadoSolicitud::PendienteAprobacion, EstadoSolicitud::Aprobada], true)
            && ($this->esAprobador($usuario) || $solicitud->solicitante_id === $usuario->id);
    }

    public function delete(Usuario $usuario, Solicitud $solicitud): bool
    {
        return $usuario->esAdminSaas() || $usuario->tieneRol(Rol::AdminOrganizacion);
    }
}
