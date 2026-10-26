<?php

namespace App\Enums;

/** activos.estado */
enum EstadoActivo: string
{
    use ConEtiqueta;

    case Operativo = 'operativo';
    case Asignado = 'asignado';
    case Degradado = 'degradado';
    case Mantenimiento = 'mantenimiento';
    case FueraServicio = 'fuera_servicio';
    case Retirado = 'retirado';

    public function label(): string
    {
        return match ($this) {
            self::Operativo => 'Operativo',
            self::Asignado => 'Asignado',
            self::Degradado => 'Degradado',
            self::Mantenimiento => 'Mantenimiento',
            self::FueraServicio => 'Fuera de servicio',
            self::Retirado => 'Retirado',
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::Operativo => 'green',
            self::Asignado => 'blue',
            self::Degradado => 'yellow',
            self::Mantenimiento => 'purple',
            self::FueraServicio => 'red',
            self::Retirado => 'gray',
        };
    }
}
