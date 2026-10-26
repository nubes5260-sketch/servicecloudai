<?php

namespace App\Services;

use App\Enums\EstadoUsuario;
use App\Enums\Rol as RolCodigo;
use App\Models\PreferenciaNotificacion;
use App\Models\Rol;
use App\Models\Solicitud;
use App\Models\Ticket;
use App\Models\TicketComentario;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuarioService
{
    /**
     * Crea o actualiza un usuario y su rol.
     * El rol va en usuarios_roles: ADMIN_SAAS sin organización; el resto con la organización del usuario.
     */
    public function guardar(?Usuario $usuario, array $datos): Usuario
    {
        return DB::transaction(function () use ($usuario, $datos) {
            $rol = RolCodigo::from($datos['rol']);
            $password = $datos['password'] ?? null;
            unset($datos['rol'], $datos['password'], $datos['password_confirmation']);
            if ($password) {
                $datos['password_hash'] = Hash::make($password);
            }

            if ($usuario === null) {
                if ($rol === RolCodigo::AdminSaas) {
                    $datos['organizacion_id'] = null;
                }
                $usuario = Usuario::create($datos);
                PreferenciaNotificacion::create(['usuario_id' => $usuario->id]);
            } else {
                $usuario->update($datos);
            }

            // Un solo rol por usuario; la BD valida que su ámbito coincida con la organización
            $usuario->roles()->sync([Rol::idDe($rol)]);
            $usuario->unsetRelation('roles');

            return $usuario;
        });
    }

    /** Elimina el usuario si no tiene historial; si lo tiene (FK RESTRICT) lo desactiva. */
    public function eliminarODesactivar(Usuario $usuario): bool
    {
        $tieneHistorial = Ticket::sinFiltroOrganizacion()->where('solicitante_id', $usuario->id)->exists()
            || TicketComentario::where('autor_id', $usuario->id)->exists()
            || Solicitud::sinFiltroOrganizacion()->where('solicitante_id', $usuario->id)->exists();

        if ($tieneHistorial) {
            $usuario->update(['estado' => EstadoUsuario::Inactivo]);

            return false;
        }

        $usuario->delete();

        return true;
    }

    /** @return array<string, string> roles que el usuario actual puede asignar */
    public function rolesAsignables(Usuario $actual, ?Usuario $editado = null): array
    {
        $roles = RolCodigo::cases();
        if ($editado && $editado->organizacion_id === null) {
            $roles = [RolCodigo::AdminSaas];
        } elseif ($editado || ! $actual->esAdminSaas()) {
            $roles = array_filter($roles, fn ($r) => $r !== RolCodigo::AdminSaas);
        }

        $opciones = [];
        foreach ($roles as $rol) {
            $opciones[$rol->value] = $rol->label();
        }

        return $opciones;
    }
}
