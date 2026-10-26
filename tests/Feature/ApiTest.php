<?php

namespace Tests\Feature;

use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActuaComo;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use ActuaComo, RefreshDatabase;

    private function token(string $email): string
    {
        return $this->postJson('/api/v1/token', ['email' => $email, 'password' => 'password', 'dispositivo' => 'tests'])
            ->assertCreated()->json('token');
    }

    public function test_emite_token_y_rechaza_credenciales_invalidas(): void
    {
        $this->postJson('/api/v1/token', ['email' => 'ana.torres@andesmarket.pe', 'password' => 'mala'])->assertUnprocessable();
        $this->postJson('/api/v1/token', ['email' => 'diego.campos@horizonte.edu.pe', 'password' => 'password'])->assertUnprocessable();

        $respuesta = $this->postJson('/api/v1/token', ['email' => 'ana.torres@andesmarket.pe', 'password' => 'password'])
            ->assertCreated()->assertJsonPath('tipo', 'Bearer')->assertJsonPath('usuario.rol', 'SUPERVISOR');
        $this->assertNotEmpty($respuesta->json('expira_en'));
    }

    public function test_sin_token_responde_401(): void
    {
        $this->getJson('/api/v1/tickets')->assertUnauthorized();
    }

    public function test_listado_y_detalle_respetan_el_tenant(): void
    {
        $token = $this->token('ana.torres@andesmarket.pe');

        $lista = $this->withToken($token)->getJson('/api/v1/tickets?por_pagina=100')->assertOk()->json('data');
        $codigos = array_column($lista, 'codigo');
        $this->assertContains('TKT-2026-00010', $codigos);
        $this->assertNotContains('TKT-2026-00009', $codigos);

        $this->withToken($token)->getJson('/api/v1/tickets/TKT-2026-00010')->assertOk()
            ->assertJsonPath('data.prioridad', 'alta')
            ->assertJsonPath('data.servicio', 'ERP Corporativo');
        $this->withToken($token)->getJson('/api/v1/tickets/TKT-2026-00009')->assertNotFound();
    }

    public function test_crear_ticket_por_api(): void
    {
        $token = $this->token('lucia.vega@andesmarket.pe');

        $this->withToken($token)->postJson('/api/v1/tickets', [
            'titulo' => 'La aplicación demora demasiado y finalmente muestra error al consultar clientes',
            'descripcion' => 'Desde la API de pruebas.', 'tipo' => 'incidente', 'impacto' => 'alto', 'urgencia' => 'media',
        ])->assertCreated()
            ->assertJsonPath('data.origen', 'api')
            ->assertJsonPath('data.prioridad', 'alta') // alto × media
            ->assertJsonStructure(['data' => ['codigo', 'tecnico', 'sla' => ['vence_respuesta', 'vence_resolucion']]]);

        $this->withToken($token)->postJson('/api/v1/tickets', ['titulo' => ''])->assertUnprocessable()->assertJsonValidationErrors(['titulo', 'descripcion']);
    }

    public function test_revocar_token(): void
    {
        $token = $this->token('ana.torres@andesmarket.pe');
        $this->withToken($token)->deleteJson('/api/v1/token')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/yo')->assertUnauthorized();
    }

    public function test_solicitante_no_ve_tickets_ajenos_por_api(): void
    {
        $token = $this->token('daniela.paredes@andesmarket.pe');
        $codigos = array_column($this->withToken($token)->getJson('/api/v1/tickets?por_pagina=100')->json('data'), 'codigo');

        $this->assertNotContains('TKT-2026-00010', $codigos);
        $this->assertTrue(collect($codigos)->every(fn ($c) => Ticket::where('codigo', $c)->value('solicitante_id') === $this->usuario('daniela.paredes@andesmarket.pe')->id));
    }
}
