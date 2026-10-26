<?php

namespace App\Services;

use App\Enums\EstadoTicket;
use App\Enums\Prioridad;
use App\Enums\Rol;
use App\Models\ConfiguracionOrganizacion;
use App\Models\Usuario;
use Illuminate\Support\Collection;

/**
 * Carga de trabajo de los técnicos de una organización.
 * Carga = tickets activos (abierto, asignado, en progreso, en espera); a igual cantidad,
 * pesa más quien tiene más tickets críticos o altos.
 */
class CargaTecnicos
{
    public const ESTADOS_ACTIVOS = [EstadoTicket::Abierto, EstadoTicket::Asignado, EstadoTicket::EnProgreso, EstadoTicket::EnEspera];

    public const MAXIMO_POR_DEFECTO = 8;

    /** @return Collection<int, array{id: int, nombre: string, iniciales: string, activos: int, urgentes: int, alta_carga: bool}> */
    public function de(int $organizacionId): Collection
    {
        $maximo = $this->configuracion($organizacionId)['maximo'];

        return Usuario::sinFiltroOrganizacion()
            ->where('organizacion_id', $organizacionId)->where('estado', 'activo')
            ->whereHas('roles', fn ($q) => $q->where('codigo', Rol::Tecnico->value))
            ->withCount([
                'ticketsAsignados as activos' => fn ($q) => $q->withoutGlobalScopes()->whereIn('estado', self::ESTADOS_ACTIVOS),
                'ticketsAsignados as urgentes' => fn ($q) => $q->withoutGlobalScopes()->whereIn('estado', self::ESTADOS_ACTIVOS)
                    ->whereIn('prioridad', [Prioridad::Critica, Prioridad::Alta]),
            ])
            ->orderBy('nombre')
            ->get()
            ->map(fn (Usuario $u) => [
                'id' => $u->id, 'nombre' => $u->nombre, 'iniciales' => $u->iniciales(),
                'activos' => (int) $u->activos, 'urgentes' => (int) $u->urgentes,
                'alta_carga' => (int) $u->activos >= $maximo,
            ])
            ->values();
    }

    /** Técnico con menor carga (opcionalmente excluyendo al actual). */
    public function elegir(int $organizacionId, ?int $excluir = null): ?array
    {
        return $this->de($organizacionId)
            ->reject(fn ($t) => $t['id'] === $excluir)
            ->sortBy([['activos', 'asc'], ['urgentes', 'asc'], ['nombre', 'asc']])
            ->first();
    }

    /** @return array{automatica: bool, maximo: int} */
    public function configuracion(int $organizacionId): array
    {
        $config = ConfiguracionOrganizacion::find($organizacionId)?->configuracion ?? [];

        return [
            'automatica' => (bool) ($config['asignacion_automatica'] ?? true),
            'maximo' => (int) ($config['carga_maxima'] ?? self::MAXIMO_POR_DEFECTO),
        ];
    }
}
