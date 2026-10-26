<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Organizacion;
use App\Models\Usuario;

/** ADMIN_SAAS: CRUD. ADMIN_ORGANIZACION: solo ve la suya. */
class OrganizacionPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->esAdminSaas() || $usuario->tieneRol(Rol::AdminOrganizacion);
    }

    public function view(Usuario $usuario, Organizacion $organizacion): bool
    {
        return $usuario->esAdminSaas()
            || ($usuario->tieneRol(Rol::AdminOrganizacion) && $usuario->organizacion_id === $organizacion->id);
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->esAdminSaas();
    }

    public function update(Usuario $usuario, Organizacion $organizacion): bool
    {
        return $usuario->esAdminSaas();
    }

    public function delete(Usuario $usuario, Organizacion $organizacion): bool
    {
        return $usuario->esAdminSaas();
    }
}
