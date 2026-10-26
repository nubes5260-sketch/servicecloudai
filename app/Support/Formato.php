<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/** Formatos de presentación usados en la UI (mismos que el mockup). */
class Formato
{
    /** 03/10/2026 21:18 */
    public static function fechaHora(?DateTimeInterface $fecha): string
    {
        return $fecha ? Carbon::instance($fecha)->format('d/m/Y H:i') : '—';
    }

    /** 03/10/2026 */
    public static function fecha(?DateTimeInterface $fecha): string
    {
        return $fecha ? Carbon::instance($fecha)->format('d/m/Y') : '—';
    }

    /** S/ 84,000 (o S/ 84,000.50 si tiene céntimos) */
    public static function moneda(float|string|null $monto): string
    {
        $monto = (float) $monto;
        $decimales = floor($monto) == $monto ? 0 : 2;

        return 'S/ '.number_format($monto, $decimales, '.', ',');
    }

    /** 01h 12m · 00h 21m · 2d 03h */
    public static function duracion(int $minutos): string
    {
        $minutos = max(0, $minutos);
        if ($minutos >= 2880) {
            return sprintf('%dd %02dh', intdiv($minutos, 1440), intdiv($minutos % 1440, 60));
        }

        return sprintf('%02dh %02dm', intdiv($minutos, 60), $minutos % 60);
    }

    /** "Hace 3 min", "Hace 2 días"... */
    public static function relativo(?DateTimeInterface $fecha): string
    {
        if (! $fecha) {
            return '—';
        }

        return ucfirst(Carbon::instance($fecha)->locale('es')->diffForHumans());
    }
}
