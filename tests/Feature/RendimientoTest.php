<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActuaComo;
use Tests\TestCase;

/** QA: los listados no deben tener consultas N+1 (máx. ~25 consultas por página, incluido el layout). */
class RendimientoTest extends TestCase
{
    use ActuaComo, RefreshDatabase;

    private const PAGINAS = [
        '/', '/tickets', '/tickets/TKT-2026-00010', '/asignaciones', '/organizaciones', '/usuarios', '/activos', '/servicios',
        '/contratos', '/incidencias', '/problemas', '/solicitudes', '/sla', '/conocimiento',
        '/notificaciones', '/reportes', '/configuracion',
    ];

    public function test_consultas_por_pagina(): void
    {
        $paginas = self::PAGINAS;
        $this->como('admin@servicecloud.ai');
        $resultado = [];
        foreach ($paginas as $pagina) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get($pagina)->assertOk();
            $resultado[$pagina] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }
        fwrite(STDERR, "\nConsultas por página (Admin SaaS): ".json_encode($resultado)."\n");

        // Listados: ≤ 15 (no crecen con el número de filas). Dashboard sin caché y detalle de ticket: ≤ 30.
        foreach ($resultado as $pagina => $consultas) {
            $maximo = in_array($pagina, ['/', '/tickets/TKT-2026-00010'], true) ? 30 : 15;
            $this->assertLessThanOrEqual($maximo, $consultas, "La página {$pagina} hace {$consultas} consultas (¿N+1?)");
        }
    }
}
