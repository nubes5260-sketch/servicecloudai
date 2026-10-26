<?php

namespace Tests\Feature;

use App\Models\Incidencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActuaComo;
use Tests\TestCase;

/**
 * Aislamiento entre organizaciones: un técnico de Grupo NovaTech no puede ver nada de AndesMarket
 * por URL, búsqueda global, CSV, endpoints JSON ni API.
 */
class AislamientoQaTest extends TestCase
{
    use ActuaComo, RefreshDatabase;

    private const TICKET_ANDES = 'TKT-2026-00010';

    public function test_tecnico_de_novatech_no_accede_a_un_ticket_de_andesmarket_por_ninguna_via(): void
    {
        $this->como('luis.ramirez@novatech.pe');

        // URL directa (detalle, edición, adjuntos, cambio de estado, kanban)
        $this->get('/tickets/'.self::TICKET_ANDES)->assertNotFound();
        $this->get('/tickets/'.self::TICKET_ANDES.'/edit')->assertNotFound();
        $this->patchJson('/tickets/'.self::TICKET_ANDES.'/estado', ['estado' => 'resuelto', 'solucion' => 'x'])->assertNotFound();
        $this->patchJson('/asignaciones/'.self::TICKET_ANDES, ['columna' => 'en_espera'])->assertNotFound();

        // Búsqueda global
        $this->getJson('/buscar?q=00010')->assertOk()->assertExactJson(['resultados' => []]);
        $this->getJson('/buscar?q=consultar clientes')->assertOk()->assertJsonMissing(['titulo' => self::TICKET_ANDES]);

        // CSV
        $csv = $this->get('/tickets/exportar')->assertOk()->streamedContent();
        $this->assertStringNotContainsString(self::TICKET_ANDES, $csv);
        $this->assertStringNotContainsString('AndesMarket', $csv);

        // Otros módulos
        $this->get('/incidencias/'.Incidencia::sinFiltroOrganizacion()->where('codigo', 'INC-0001')->value('id'))->assertNotFound();
        $this->get('/conocimiento/KB-007')->assertNotFound();
        $this->get('/activos')->assertOk()->assertDontSee('DB-PROD-01');
    }

    public function test_aislamiento_por_api(): void
    {
        $token = $this->postJson('/api/v1/token', ['email' => 'luis.ramirez@novatech.pe', 'password' => 'password'])->json('token');

        $this->withToken($token)->getJson('/api/v1/tickets/'.self::TICKET_ANDES)->assertNotFound();
        $codigos = array_column($this->withToken($token)->getJson('/api/v1/tickets?por_pagina=100')->json('data'), 'codigo');
        $this->assertNotContains(self::TICKET_ANDES, $codigos);
    }
}
