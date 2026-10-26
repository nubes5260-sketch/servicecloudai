<?php

namespace App\Enums;

/** tickets.tipo */
enum TipoTicket: string
{
    use ConEtiqueta;

    case Incidente = 'incidente';
    case Solicitud = 'solicitud';
    case Consulta = 'consulta';
    case Cambio = 'cambio';

    public function label(): string
    {
        return match ($this) {
            self::Incidente => 'Incidente',
            self::Solicitud => 'Solicitud',
            self::Consulta => 'Consulta',
            self::Cambio => 'Cambio',
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::Incidente => 'red',
            self::Solicitud => 'blue',
            self::Consulta => 'cyan',
            self::Cambio => 'purple',
        };
    }
}
