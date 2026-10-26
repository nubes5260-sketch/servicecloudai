<?php

namespace App\Models\Concerns;

use App\Models\Organizacion;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Aislamiento multi-tenant: filtra por la organización del usuario autenticado
 * y asigna organizacion_id al crear. ADMIN_SAAS y procesos sin usuario
 * (consola, colas, seeders) ven todo.
 *
 * Los modelos cuyo organizacion_id puede ser NULL para registros de plataforma
 * (plantillas SLA, artículos públicos...) definen
 * `protected static bool $incluyeGlobales = true;` para leerlos también.
 */
trait PerteneceAOrganizacion
{
    public static function bootPerteneceAOrganizacion(): void
    {
        static::addGlobalScope('organizacion', function (Builder $consulta) {
            $usuario = static::usuarioTenant();
            if ($usuario === null || $usuario->esAdminSaas()) {
                return;
            }

            $columna = $consulta->getModel()->qualifyColumn('organizacion_id');
            $incluyeGlobales = property_exists(static::class, 'incluyeGlobales') && static::$incluyeGlobales;

            $consulta->where(function (Builder $q) use ($columna, $usuario, $incluyeGlobales) {
                $q->where($columna, $usuario->organizacion_id);
                if ($incluyeGlobales) {
                    $q->orWhereNull($columna);
                }
            });
        });

        static::creating(function ($modelo) {
            $usuario = static::usuarioTenant();
            if ($usuario !== null && ! $usuario->esAdminSaas()) {
                // Nunca se confía en el organizacion_id que llegue del formulario.
                $modelo->organizacion_id = $usuario->organizacion_id;
            }
        });
    }

    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class, 'organizacion_id');
    }

    /** Quita el filtro de organización (uso interno: procesos de sistema). */
    public static function sinFiltroOrganizacion(): Builder
    {
        return static::withoutGlobalScope('organizacion');
    }

    /**
     * Usuario autenticado ya resuelto. Se usa hasUser() para no disparar la
     * carga del usuario desde aquí (Usuario también usa este trait).
     */
    protected static function usuarioTenant(): ?Usuario
    {
        $guard = Auth::guard();
        if (! $guard->hasUser()) {
            return null;
        }
        $usuario = $guard->user();

        return $usuario instanceof Usuario ? $usuario : null;
    }
}
