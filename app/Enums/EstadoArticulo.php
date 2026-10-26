<?php

namespace App\Enums;

/** articulos_conocimiento.estado */
enum EstadoArticulo: string
{
    use ConEtiqueta;

    case Borrador = 'borrador';
    case Publicado = 'publicado';
    case Archivado = 'archivado';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Publicado => 'Publicado',
            self::Archivado => 'Archivado',
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::Borrador => 'yellow',
            self::Publicado => 'green',
            self::Archivado => 'gray',
        };
    }
}
