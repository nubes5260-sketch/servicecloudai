<?php

namespace App\Enums;

/** usuarios.estado */
enum EstadoUsuario: string
{
    use ConEtiqueta;

    case Activo = 'activo';
    case Inactivo = 'inactivo';
    case Bloqueado = 'bloqueado';

    public function label(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Inactivo => 'Inactivo',
            self::Bloqueado => 'Bloqueado',
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::Activo => 'green',
            self::Inactivo => 'gray',
            self::Bloqueado => 'red',
        };
    }
}
