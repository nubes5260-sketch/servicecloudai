<?php

namespace App\Support;

/** Mapa de colores de la función badge() del mockup, para textos que no son enums (planes, SLA). */
class ColorBadge
{
    private const MAPA = [
        'Crítica' => 'red', 'Alta' => 'yellow', 'Media' => 'blue', 'Baja' => 'gray',
        'Abierto' => 'blue', 'En progreso' => 'purple', 'En espera' => 'yellow', 'Resuelto' => 'green',
        'Activo' => 'green', 'Inactivo' => 'gray', 'En evaluación' => 'yellow',
        'Operativo' => 'green', 'Asignado' => 'blue', 'Degradado' => 'yellow', 'Mantenimiento' => 'purple', 'Fuera de servicio' => 'red',
        'Interrumpido' => 'red', 'Vigente' => 'green', 'Por vencer' => 'yellow', 'Vencido' => 'red',
        'Investigando' => 'purple', 'Monitoreando' => 'cyan', 'Con workaround' => 'yellow', 'Cerrado' => 'green',
        'Pendiente aprobación' => 'yellow', 'Completado' => 'green', 'Rechazado' => 'red',
        'Gold' => 'yellow', 'Silver' => 'gray', 'Platinum' => 'purple', 'Starter' => 'blue', 'Business' => 'cyan', 'Enterprise' => 'purple',
        'Alto' => 'red', 'Medio' => 'yellow', 'Bajo' => 'green',
    ];

    public static function de(?string $texto): string
    {
        return self::MAPA[(string) $texto] ?? 'blue';
    }
}
