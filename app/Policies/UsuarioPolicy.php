<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Usuario;

/** ADMIN_SAAS: todas las organizaciones. ADMIN_ORGANIZACION: su organización. SUPERVISOR: solo ver. */
class UsuarioPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->puedeVerModulo('usuarios');
    }

    public function view(Usuario $usuario, Usuario $otro): bool
    {
        return $this->viewAny($usuario) && ($usuario->esAdminSaas() || $otro->organizacion_id === $usuario->organizacion_id);
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->esAdminSaas() || $usuario->tieneRol(Rol::AdminOrganizacion);
    }

    public function update(Usuario $usuario, Usuario $otro): bool
    {
        if ($usuario->esAdminSaas()) {
            return true;
        }

        return $usuario->tieneRol(Rol::AdminOrganizacion)
            && $otro->organizacion_id === $usuario->organizacion_id
            && ! $otro->esAdminSaas();
    }

    public function delete(Usuario $usuario, Usuario $otro): bool
    {
        return $usuario->id !== $otro->id && $this->update($usuario, $otro);
    }
}
