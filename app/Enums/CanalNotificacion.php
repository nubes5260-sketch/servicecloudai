<?php

namespace App\Enums;

/** notificaciones.canal */
enum CanalNotificacion: string
{
    use ConEtiqueta;

    case InApp = 'in_app';
    case Email = 'email';
    case Push = 'push';

    public function label(): string
    {
        return match ($this) {
            self::InApp => 'En la app',
            self::Email => 'Email',
            self::Push => 'Push',
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::InApp => 'blue',
            self::Email => 'cyan',
            self::Push => 'purple',
        };
    }
}
