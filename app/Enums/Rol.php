<?php

namespace App\Enums;

/** Códigos de la tabla roles */
enum Rol: string
{
    use ConEtiqueta;

    case AdminSaas = 'ADMIN_SAAS';
    case AdminOrganizacion = 'ADMIN_ORGANIZACION';
    case Supervisor = 'SUPERVISOR';
    case Tecnico = 'TECNICO';
    case Solicitante = 'SOLICITANTE';

    public function label(): string
    {
        return match ($this) {
            self::AdminSaas => 'Administrador SaaS',
            self::AdminOrganizacion => 'Administrador de organización',
            self::Supervisor => 'Supervisor',
            self::Tecnico => 'Técnico de soporte',
            self::Solicitante => 'Solicitante',
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::AdminSaas => 'purple',
            self::AdminOrganizacion => 'blue',
            self::Supervisor => 'cyan',
            self::Tecnico => 'green',
            self::Solicitante => 'gray',
        };
    }
}
