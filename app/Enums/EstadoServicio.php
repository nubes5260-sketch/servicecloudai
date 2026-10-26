<?php

namespace App\Enums;

/** servicios.estado */
enum EstadoServicio: string
{
    use ConEtiqueta;

    case Operativo = 'operativo';
    case Degradado = 'degradado';
    case Interrumpido = 'interrumpido';
    case Mantenimiento = 'mantenimiento';
    case Retirado = 'retirado';

    public function label(): string
    {
        return match ($this) {
            self::Operativo => 'Operativo',
            self::Degradado => 'Degradado',
            self::Interrumpido => 'Interrumpido',
            self::Mantenimiento => 'Mantenimiento',
            self::Retirado => 'Retirado',
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::Operativo => 'green',
            self::Degradado => 'yellow',
            self::Interrumpido => 'red',
            self::Mantenimiento => 'purple',
            self::Retirado => 'gray',
        };
    }
}
