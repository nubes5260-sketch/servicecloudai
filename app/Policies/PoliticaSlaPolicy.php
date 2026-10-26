<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\PoliticaSla;
use App\Models\Usuario;

/** ADMIN_SAAS: todas (incluidas las plantillas globales). ADMIN_ORGANIZACION: las de su organización. SUPERVISOR: ver. */
class PoliticaSlaPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->puedeVerModulo('sla');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->esAdminSaas() || $usuario->tieneRol(Rol::AdminOrganizacion);
    }

    public function update(Usuario $usuario, PoliticaSla $politica): bool
    {
        if ($usuario->esAdminSaas()) {
            return true;
        }

        return $usuario->tieneRol(Rol::AdminOrganizacion) && $politica->organizacion_id === $usuario->organizacion_id;
    }

    public function delete(Usuario $usuario, PoliticaSla $politica): bool
    {
        return $this->update($usuario, $politica);
    }
}
