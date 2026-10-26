<?php

namespace App\Enums;

/** solicitudes.estado */
enum EstadoSolicitud: string
{
    use ConEtiqueta;

    case PendienteAprobacion = 'pendiente_aprobacion';
    case Aprobada = 'aprobada';
    case Rechazada = 'rechazada';
    case EnProgreso = 'en_progreso';
    case Completada = 'completada';
    case Cancelada = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::PendienteAprobacion => 'Pendiente aprobación',
            self::Aprobada => 'Aprobada',
            self::Rechazada => 'Rechazada',
            self::EnProgreso => 'En progreso',
            self::Completada => 'Completada',
            self::Cancelada => 'Cancelada',
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::PendienteAprobacion => 'yellow',
            self::Aprobada => 'cyan',
            self::Rechazada => 'red',
            self::EnProgreso => 'purple',
            self::Completada => 'green',
            self::Cancelada => 'gray',
        };
    }
}
