<?php

namespace App\Enums;

/** articulos_conocimiento.visibilidad */
enum VisibilidadArticulo: string
{
    use ConEtiqueta;

    case Publica = 'publica';
    case Organizacion = 'organizacion';
    case Interna = 'interna';

    public function label(): string
    {
        return match ($this) {
            self::Publica => 'Pública',
            self::Organizacion => 'Organización',
            self::Interna => 'Interna',
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::Publica => 'green',
            self::Organizacion => 'blue',
            self::Interna => 'purple',
        };
    }
}
