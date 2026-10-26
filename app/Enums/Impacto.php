<?php

namespace App\Enums;

/** Impacto de tickets e incidencias */
enum Impacto: string
{
    use ConEtiqueta;

    case Bajo = 'bajo';
    case Medio = 'medio';
    case Alto = 'alto';

    public function label(): string
    {
        return match ($this) {
            self::Bajo => 'Bajo',
            self::Medio => 'Medio',
            self::Alto => 'Alto',
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::Bajo => 'green',
            self::Medio => 'yellow',
            self::Alto => 'red',
        };
    }
}
