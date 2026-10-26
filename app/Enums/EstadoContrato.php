<?php

namespace App\Enums;

/** contratos.estado */
enum EstadoContrato: string
{
    use ConEtiqueta;

    case Borrador = 'borrador';
    case Vigente = 'vigente';
    case PorVencer = 'por_vencer';
    case Vencido = 'vencido';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Vigente => 'Vigente',
            self::PorVencer => 'Por vencer',
            self::Vencido => 'Vencido',
            self::Cancelado => 'Cancelado',
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::Borrador => 'gray',
            self::Vigente => 'green',
            self::PorVencer => 'yellow',
            self::Vencido => 'red',
            self::Cancelado => 'gray',
        };
    }
}
