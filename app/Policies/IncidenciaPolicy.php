<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;

/** Incidencias y problemas: gestión todo; técnico ve y actualiza los que tiene a cargo. */
class IncidenciaPolicy
{
    protected function esGestion(Usuario $usuario): bool
    {
        return $usuario->esAdminSaas() || $usuario->tieneRol(Rol::AdminOrganizacion, Rol::Supervisor);
    }

    protected function modulo(): string
    {
        return 'incidencias';
    }

    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->puedeVerModulo($this->modulo());
    }

    public function view(Usuario $usuario, Model $modelo): bool
    {
        return $this->viewAny($usuario);
    }

    public function create(Usuario $usuario): bool
    {
        return $this->esGestion($usuario);
    }

    public function update(Usuario $usuario, Model $modelo): bool
    {
        return $this->esGestion($usuario) || ($usuario->tieneRol(Rol::Tecnico) && $modelo->responsable_id === $usuario->id);
    }

    /** Cambiar responsable o datos de planificación: solo gestión. */
    public function gestionar(Usuario $usuario, ?Model $modelo = null): bool
    {
        return $this->esGestion($usuario);
    }

    public function delete(Usuario $usuario, Model $modelo): bool
    {
        return $this->esGestion($usuario);
    }
}
