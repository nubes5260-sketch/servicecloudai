<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** Exportación CSV del servidor: UTF-8 con BOM y nombre servicecloud_<modulo>_<fecha>.csv (como exportCSV() del mockup). */
class Csv
{
    /**
     * @param  list<string>  $encabezados
     * @param  iterable<array<int, mixed>>  $filas
     */
    public static function descargar(string $modulo, array $encabezados, iterable $filas): StreamedResponse
    {
        $nombre = 'servicecloud_'.$modulo.'_'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($encabezados, $filas) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, $encabezados, ',', '"', '');
            foreach ($filas as $fila) {
                fputcsv($salida, array_map(fn ($v) => self::texto($v), $fila), ',', '"', '');
            }
            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private static function texto(mixed $valor): string
    {
        $texto = match (true) {
            $valor instanceof \BackedEnum && method_exists($valor, 'label') => $valor->label(),
            $valor instanceof \DateTimeInterface => Formato::fechaHora($valor),
            is_bool($valor) => $valor ? 'Sí' : 'No',
            default => (string) ($valor ?? ''),
        };

        // Evita inyección de fórmulas al abrir el CSV en Excel
        return preg_match('/^[=+\-@]/', $texto) ? "'".$texto : $texto;
    }
}
