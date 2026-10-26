<?php

namespace App\Enums;

/** Urgencia de tickets e incidencias */
enum Urgencia: string
{
    use ConEtiqueta;

    case Baja = 'baja';
    case Media = 'media';
    case Alta = 'alta';

    public function label(): string
    {
        return match ($this) {
            self::Baja => 'Baja',
            self::Media => 'Media',
            self::Alta => 'Alta',
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::Baja => 'gray',
            self::Media => 'blue',
            self::Alta => 'yellow',
        };
    }
}
