<?php

namespace App\Enums;

/** reglas_sla.horario_servicio */
enum HorarioServicio: string
{
    use ConEtiqueta;

    case Continuo = '24x7';
    case Laboral = 'laboral';

    public function label(): string
    {
        return match ($this) {
            self::Continuo => '24x7',
            self::Laboral => 'Laboral (L-V 08:00-18:00)',
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::Continuo => 'cyan',
            self::Laboral => 'blue',
        };
    }
}
