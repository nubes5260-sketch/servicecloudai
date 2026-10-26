<?php

namespace App\Policies;

use App\Enums\EstadoArticulo;
use App\Enums\Rol;
use App\Enums\VisibilidadArticulo;
use App\Models\ArticuloConocimiento;
use App\Models\Usuario;

/**
 * Regla 03: ADMIN_SAAS, ADMIN_ORGANIZACION y SUPERVISOR gestionan; TECNICO crea borradores y ve;
 * SOLICITANTE ve los publicados (públicos o de su organización, nunca internos).
 * Los artículos públicos de plataforma (sin organización) solo los edita el ADMIN_SAAS.
 */
class ArticuloConocimientoPolicy
{
    private function esGestion(Usuario $usuario): bool
    {
        return $usuario->esAdminSaas() || $usuario->tieneRol(Rol::AdminOrganizacion, Rol::Supervisor);
    }

    private function esPersonal(Usuario $usuario): bool
    {
        return $this->esGestion($usuario) || $usuario->tieneRol(Rol::Tecnico);
    }

    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->puedeVerModulo('conocimiento');
    }

    public function view(Usuario $usuario, ArticuloConocimiento $articulo): bool
    {
        if ($articulo->estado === EstadoArticulo::Publicado) {
            return $this->esPersonal($usuario) || $articulo->visibilidad !== VisibilidadArticulo::Interna;
        }

        // Borradores y archivados: gestión de la organización o su autor
        return $this->esGestion($usuario) || $articulo->autor_id === $usuario->id;
    }

    public function create(Usuario $usuario): bool
    {
        return $this->esPersonal($usuario);
    }

    /** Publicar o archivar (los técnicos solo dejan borradores). */
    public function publicar(Usuario $usuario, ?ArticuloConocimiento $articulo = null): bool
    {
        return $this->esGestion($usuario);
    }

    public function update(Usuario $usuario, ArticuloConocimiento $articulo): bool
    {
        if ($usuario->esAdminSaas()) {
            return true;
        }
        if ($articulo->organizacion_id === null) {
            return false; // artículos públicos de plataforma
        }
        if ($this->esGestion($usuario)) {
            return $articulo->organizacion_id === $usuario->organizacion_id;
        }

        return $usuario->tieneRol(Rol::Tecnico) && $articulo->autor_id === $usuario->id && $articulo->estado === EstadoArticulo::Borrador;
    }

    public function delete(Usuario $usuario, ArticuloConocimiento $articulo): bool
    {
        return $this->esGestion($usuario) && $this->update($usuario, $articulo);
    }
}
