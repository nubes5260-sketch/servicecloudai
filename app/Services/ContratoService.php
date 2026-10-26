<?php

namespace App\Services;

use App\Enums\EstadoContrato;
use App\Models\Contrato;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class ContratoService
{
    public const DIAS_POR_VENCER = 30;

    /**
     * Estado según las fechas. Borrador y cancelado son decisiones manuales y se respetan;
     * el resto se recalcula: vencido, por vencer (≤ 30 días) o vigente.
     */
    public function estadoPorFechas(DateTimeInterface $fin, EstadoContrato $estadoActual, ?DateTimeInterface $hoy = null): EstadoContrato
    {
        if (in_array($estadoActual, [EstadoContrato::Borrador, EstadoContrato::Cancelado], true)) {
            return $estadoActual;
        }
        $hoy = CarbonImmutable::instance($hoy ?? now())->startOfDay();
        $fin = CarbonImmutable::instance($fin)->startOfDay();

        return match (true) {
            $fin < $hoy => EstadoContrato::Vencido,
            $fin <= $hoy->addDays(self::DIAS_POR_VENCER) => EstadoContrato::PorVencer,
            default => EstadoContrato::Vigente,
        };
    }

    /** Recalcula el estado de todos los contratos. Devuelve cuántos cambiaron. */
    public function actualizarEstados(): int
    {
        $cambios = 0;
        Contrato::sinFiltroOrganizacion()
            ->whereIn('estado', [EstadoContrato::Vigente, EstadoContrato::PorVencer, EstadoContrato::Vencido])
            ->each(function (Contrato $contrato) use (&$cambios) {
                $nuevo = $this->estadoPorFechas($contrato->fecha_fin, $contrato->estado);
                if ($nuevo !== $contrato->estado) {
                    $contrato->update(['estado' => $nuevo]);
                    $cambios++;
                }
            });

        return $cambios;
    }
}
