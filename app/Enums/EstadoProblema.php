<?php

namespace App\Enums;

/** problemas.estado */
enum EstadoProblema: string
{
    use ConEtiqueta;

    case Abierto = 'abierto';
    case EnAnalisis = 'en_analisis';
    case ConWorkaround = 'con_workaround';
    case Resuelto = 'resuelto';
    case Cerrado = 'cerrado';

    public function label(): string
    {
        return match ($this) {
            self::Abierto => 'Abierto',
            self::EnAnalisis => 'En análisis',
            self::ConWorkaround => 'Con workaround',
            self::Resuelto => 'Resuelto',
            self::Cerrado => 'Cerrado',
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::Abierto => 'blue',
            self::EnAnalisis => 'purple',
            self::ConWorkaround => 'yellow',
            self::Resuelto => 'green',
            self::Cerrado => 'green',
        };
    }
}
