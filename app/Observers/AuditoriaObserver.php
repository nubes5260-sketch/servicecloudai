<?php

namespace App\Observers;

use App\Models\Auditoria;
use App\Models\Organizacion;
use App\Models\ReglaSla;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Registra en `auditoria` las altas, cambios y bajas de organizaciones, usuarios,
 * contratos, SLA y tickets (regla 03). Solo cuando actúa un usuario autenticado:
 * seeders, comandos y el programador no generan ruido.
 */
class AuditoriaObserver
{
    /** Campos que nunca se guardan en la auditoría. */
    private const OCULTOS = ['password_hash', 'remember_token', 'actualizado_en', 'creado_en'];

    public function created(Model $modelo): void
    {
        $this->registrar($modelo, 'crear', null, $this->limpiar($modelo->getAttributes()));
    }

    public function updated(Model $modelo): void
    {
        $cambios = $this->limpiar($modelo->getChanges());
        if (array_key_exists('password_hash', $modelo->getChanges())) {
            $cambios['password'] = '(actualizada)'; // se audita el cambio, nunca el hash
        }
        if ($cambios === []) {
            return;
        }
        $antes = array_intersect_key($this->limpiar($modelo->getRawOriginal()), $cambios);
        $this->registrar($modelo, 'actualizar', $antes, $cambios);
    }

    public function deleted(Model $modelo): void
    {
        $this->registrar($modelo, 'eliminar', $this->limpiar($modelo->getRawOriginal()), null);
    }

    private function registrar(Model $modelo, string $accion, ?array $antes, ?array $despues): void
    {
        $usuario = Auth::guard()->hasUser() ? Auth::user() : null;
        if (! $usuario) {
            return;
        }

        Auditoria::create([
            'organizacion_id' => $this->organizacionDe($modelo, $accion),
            'usuario_id' => $usuario->id,
            'entidad' => $modelo->getTable(),
            'entidad_id' => $modelo->getKey(),
            'accion' => $accion,
            'datos_antes' => $antes,
            'datos_despues' => $despues,
            'ip_origen' => request()?->ip(),
        ]);
    }

    private function organizacionDe(Model $modelo, string $accion): ?int
    {
        if ($modelo instanceof Organizacion) {
            return $accion === 'eliminar' ? null : $modelo->id; // la FK no admite una organización ya borrada
        }
        if ($modelo instanceof ReglaSla) {
            return $modelo->politica()->withoutGlobalScopes()->value('organizacion_id');
        }

        return $modelo->getAttribute('organizacion_id');
    }

    private function limpiar(array $datos): array
    {
        return array_diff_key($datos, array_flip(self::OCULTOS));
    }
}
