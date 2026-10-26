<?php

namespace App\Enums;

/**
 * Utilidades comunes a los enums de la UI: valores para validación
 * y opciones (valor => etiqueta) para los <select>.
 */
trait ConEtiqueta
{
    /** @return list<string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<string, string> */
    public static function opciones(): array
    {
        $opciones = [];
        foreach (self::cases() as $caso) {
            $opciones[$caso->value] = $caso->label();
        }

        return $opciones;
    }

    public static function etiquetaDe(?string $valor): string
    {
        return $valor === null ? '—' : (self::tryFrom($valor)?->label() ?? $valor);
    }
}
