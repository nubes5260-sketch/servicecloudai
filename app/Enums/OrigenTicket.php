<?php

namespace App\Enums;

/** tickets.origen */
enum OrigenTicket: string
{
    use ConEtiqueta;

    case Portal = 'portal';
    case Email = 'email';
    case Telefono = 'telefono';
    case Api = 'api';
    case Monitorizacion = 'monitorizacion';

    public function label(): string
    {
        return match ($this) {
            self::Portal => 'Portal',
            self::Email => 'Email',
            self::Telefono => 'Teléfono',
            self::Api => 'API',
            self::Monitorizacion => 'Monitorización',
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::Portal => 'blue',
            self::Email => 'blue',
            self::Telefono => 'blue',
            self::Api => 'cyan',
            self::Monitorizacion => 'purple',
        };
    }
}
