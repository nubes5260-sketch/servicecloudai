<?php

namespace App\Enums;

/** organizaciones.estado */
enum EstadoOrganizacion: string
{
    use ConEtiqueta;

    case Evaluacion = 'evaluacion';
    case Activa = 'activa';
    case Suspendida = 'suspendida';
    case Inactiva = 'inactiva';

    public function label(): string
    {
        return match ($this) {
            self::Evaluacion => 'En evaluación',
            self::Activa => 'Activa',
            self::Suspendida => 'Suspendida',
            self::Inactiva => 'Inactiva',
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::Evaluacion => 'yellow',
            self::Activa => 'green',
            self::Suspendida => 'red',
            self::Inactiva => 'gray',
        };
    }
}
