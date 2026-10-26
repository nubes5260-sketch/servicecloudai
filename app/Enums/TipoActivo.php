<?php

namespace App\Enums;

/** activos.tipo */
enum TipoActivo: string
{
    use ConEtiqueta;

    case Servidor = 'servidor';
    case Laptop = 'laptop';
    case Switch = 'switch';
    case Firewall = 'firewall';
    case VmCloud = 'vm_cloud';
    case Desktop = 'desktop';
    case Impresora = 'impresora';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Servidor => 'Servidor',
            self::Laptop => 'Laptop',
            self::Switch => 'Switch',
            self::Firewall => 'Firewall',
            self::VmCloud => 'VM Cloud',
            self::Desktop => 'Desktop',
            self::Impresora => 'Impresora',
            self::Otro => 'Otro',
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::Servidor => 'blue',
            self::Laptop => 'blue',
            self::Switch => 'blue',
            self::Firewall => 'blue',
            self::VmCloud => 'blue',
            self::Desktop => 'blue',
            self::Impresora => 'blue',
            self::Otro => 'blue',
        };
    }
}
