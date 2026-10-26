<?php

namespace App\Enums;

/** tickets.estado */
enum EstadoTicket: string
{
    use ConEtiqueta;

    case Abierto = 'abierto';
    case Asignado = 'asignado';
    case EnProgreso = 'en_progreso';
    case EnEspera = 'en_espera';
    case Resuelto = 'resuelto';
    case Cerrado = 'cerrado';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Abierto => 'Abierto',
            self::Asignado => 'Asignado',
            self::EnProgreso => 'En progreso',
            self::EnEspera => 'En espera',
            self::Resuelto => 'Resuelto',
            self::Cerrado => 'Cerrado',
            self::Cancelado => 'Cancelado',
        };
    }

    /**
     * Flujo: abierto → asignado → en_progreso ⇄ en_espera → resuelto → cerrado;
     * cancelado desde cualquier estado previo a resuelto; un resuelto puede reabrirse.
     *
     * @return list<self>
     */
    public function transiciones(): array
    {
        return match ($this) {
            self::Abierto => [self::Asignado, self::EnProgreso, self::Cancelado],
            self::Asignado => [self::Abierto, self::EnProgreso, self::EnEspera, self::Cancelado],
            self::EnProgreso => [self::EnEspera, self::Resuelto, self::Cancelado],
            self::EnEspera => [self::EnProgreso, self::Resuelto, self::Cancelado],
            self::Resuelto => [self::Cerrado, self::EnProgreso],
            self::Cerrado, self::Cancelado => [],
        };
    }

    public function puedePasarA(self $destino): bool
    {
        return in_array($destino, $this->transiciones(), true);
    }

    /** Estados que siguen consumiendo SLA. */
    public function esActivo(): bool
    {
        return ! in_array($this, [self::Resuelto, self::Cerrado, self::Cancelado], true);
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::Abierto => 'blue',
            self::Asignado => 'blue',
            self::EnProgreso => 'purple',
            self::EnEspera => 'yellow',
            self::Resuelto => 'green',
            self::Cerrado => 'green',
            self::Cancelado => 'gray',
        };
    }
}
