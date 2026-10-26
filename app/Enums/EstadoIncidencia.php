<?php

namespace App\Enums;

/** incidencias.estado */
enum EstadoIncidencia: string
{
    use ConEtiqueta;

    case Abierta = 'abierta';
    case Investigando = 'investigando';
    case EnProgreso = 'en_progreso';
    case Monitoreando = 'monitoreando';
    case Resuelta = 'resuelta';
    case Cerrada = 'cerrada';

    public function label(): string
    {
        return match ($this) {
            self::Abierta => 'Abierta',
            self::Investigando => 'Investigando',
            self::EnProgreso => 'En progreso',
            self::Monitoreando => 'Monitoreando',
            self::Resuelta => 'Resuelta',
            self::Cerrada => 'Cerrada',
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::Abierta => 'blue',
            self::Investigando => 'purple',
            self::EnProgreso => 'purple',
            self::Monitoreando => 'cyan',
            self::Resuelta => 'green',
            self::Cerrada => 'green',
        };
    }
}
