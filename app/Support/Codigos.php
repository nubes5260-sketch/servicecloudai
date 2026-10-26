<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Códigos correlativos legibles: ORG-004, AST-0006, SRV-06, CTR-004, INC-0004, KB-009... */
class Codigos
{
    /**
     * Siguiente código para un prefijo, buscando el mayor número existente en toda la tabla
     * (sin filtro de organización, para no repetir códigos entre organizaciones).
     * Llamar dentro de una transacción junto con el INSERT (ver bloquear()).
     *
     * @param  class-string<Model>  $modelo
     */
    public static function siguiente(string $modelo, string $prefijo, int $digitos, string $columna = 'codigo'): string
    {
        $tabla = (new $modelo)->getTable();
        self::bloquear($tabla);
        $maximo = DB::table($tabla)
            ->where($columna, 'like', $prefijo.'-%')
            // ?::int: sin el cast PostgreSQL interpreta el parámetro como patrón regex, no como posición
            ->selectRaw("max(nullif(regexp_replace(substring($columna from ?::int), '[^0-9]', '', 'g'), '')::bigint) as maximo", [strlen($prefijo) + 2])
            ->value('maximo');

        return $prefijo.'-'.str_pad((string) ((int) $maximo + 1), $digitos, '0', STR_PAD_LEFT);
    }

    /**
     * Bloqueo asesor de PostgreSQL hasta el fin de la transacción actual: serializa la
     * generación de códigos entre peticiones concurrentes (varias instancias en la nube).
     */
    public static function bloquear(string $clave): void
    {
        if (DB::transactionLevel() > 0) {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['servicecloud_codigo_'.$clave]);
        }
    }
}
