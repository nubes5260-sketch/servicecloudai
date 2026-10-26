<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;

/**
 * Activos, servicios y contratos (regla 03): ADMIN_SAAS y ADMIN_ORGANIZACION
 * gestionan; SUPERVISOR y TECNICO solo ven.
 */
abstract class PoliticaMaestro
{
    abstract protected function modulo(): string;

    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->puedeVerModulo($this->modulo());
    }

    public function view(Usuario $usuario, Model $modelo): bool
    {
        return $this->viewAny($usuario) && $this->mismaOrganizacion($usuario, $modelo);
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->esAdminSaas() || $usuario->tieneRol(Rol::AdminOrganizacion);
    }

    public function update(Usuario $usuario, Model $modelo): bool
    {
        return $this->create($usuario) && $this->mismaOrganizacion($usuario, $modelo);
    }

    public function delete(Usuario $usuario, Model $modelo): bool
    {
        return $this->update($usuario, $modelo);
    }

    protected function mismaOrganizacion(Usuario $usuario, Model $modelo): bool
    {
        return $usuario->esAdminSaas() || $modelo->organizacion_id === $usuario->organizacion_id;
    }
}
