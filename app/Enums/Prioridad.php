<?php

namespace App\Enums;

/** Prioridad de tickets, incidencias, solicitudes y reglas SLA */
enum Prioridad: string
{
    use ConEtiqueta;

    case Baja = 'baja';
    case Media = 'media';
    case Alta = 'alta';
    case Critica = 'critica';

    public function label(): string
    {
        return match ($this) {
            self::Baja => 'Baja',
            self::Media => 'Media',
            self::Alta => 'Alta',
            self::Critica => 'Crítica',
        };
    }

    /** Matriz ITIL impacto × urgencia (skill servicecloud-schema). */
    public static function desdeMatriz(Impacto $impacto, Urgencia $urgencia): self
    {
        return match ($impacto) {
            Impacto::Bajo => $urgencia === Urgencia::Alta ? self::Media : self::Baja,
            Impacto::Medio => match ($urgencia) {
                Urgencia::Baja => self::Baja,
                Urgencia::Media => self::Media,
                Urgencia::Alta => self::Alta,
            },
            Impacto::Alto => match ($urgencia) {
                Urgencia::Baja => self::Media,
                Urgencia::Media => self::Alta,
                Urgencia::Alta => self::Critica,
            },
        };
    }

    /** Peso para ordenar (crítica primero). */
    public function peso(): int
    {
        return match ($this) {
            self::Critica => 4,
            self::Alta => 3,
            self::Media => 2,
            self::Baja => 1,
        };
    }

    /** Color de badge (mismo mapa que badge() del mockup). */
    public function badge(): string
    {
        return match ($this) {
            self::Baja => 'gray',
            self::Media => 'blue',
            self::Alta => 'yellow',
            self::Critica => 'red',
        };
    }
}
