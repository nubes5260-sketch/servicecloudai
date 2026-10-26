<?php

namespace Tests\Feature;

use App\Models\ArticuloConocimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActuaComo;
use Tests\TestCase;

/** Base de conocimiento: borradores, publicación, búsqueda, visibilidad y vistas. */
class ConocimientoTest extends TestCase
{
    use ActuaComo, RefreshDatabase;

    private function contenido(): string
    {
        return str_repeat('Para liberar espacio en el servidor de archivos se rotan los logs y se mueven los respaldos antiguos. ', 4);
    }

    public function test_tecnico_crea_borrador_y_supervisor_lo_publica(): void
    {
        $datos = [
            'titulo' => 'Liberar espacio en el File Server', 'categoria' => 'Hardware', 'resumen' => 'Procedimiento de limpieza.',
            'contenido' => $this->contenido(), 'visibilidad' => 'organizacion',
        ];

        // El técnico no puede publicar: solo crea borradores
        $this->como('kevin.rojas@andesmarket.pe')->ajax()->post('/conocimiento', $datos + ['estado' => 'publicado'])
            ->assertUnprocessable()->assertJsonValidationErrors('estado');
        $this->ajax()->post('/conocimiento', $datos + ['etiquetas' => 'disco, espacio'])->assertOk();

        $articulo = ArticuloConocimiento::where('titulo', 'Liberar espacio en el File Server')->firstOrFail();
        $this->assertSame('borrador', $articulo->estado->value);
        $this->assertSame('KB-009', $articulo->codigo);
        $this->assertEqualsCanonicalizing(['disco', 'espacio'], $articulo->etiquetas->pluck('nombre')->all());

        // El supervisor lo publica y sube la versión al cambiar el contenido
        $this->como('ana.torres@andesmarket.pe')->ajax()->put("/conocimiento/{$articulo->codigo}", array_merge($datos, [
            'contenido' => $this->contenido().'Revisar también la papelera.', 'estado' => 'publicado',
        ]))->assertOk();
        $articulo->refresh();
        $this->assertSame('publicado', $articulo->estado->value);
        $this->assertSame(2, $articulo->version);
        $this->como('lucia.vega@andesmarket.pe')->get('/conocimiento')->assertSee('Liberar espacio en el File Server');
    }

    public function test_busqueda_por_texto_y_categoria(): void
    {
        $this->como('lucia.vega@andesmarket.pe');

        $this->get('/conocimiento?q=pool')->assertOk()->assertSee('KB-002', false)->assertDontSee('VPN: desconexiones');
        $this->get('/conocimiento?categoria=Redes')->assertOk()->assertSee('VPN: desconexiones')->assertDontSee('Optimización de consultas SQL');
        $this->get('/conocimiento?q=zzzz-sin-resultados')->assertOk()->assertSee('Sin resultados');
    }

    public function test_ver_articulo_suma_vistas_y_respeta_la_visibilidad(): void
    {
        $articulo = ArticuloConocimiento::where('codigo', 'KB-002')->firstOrFail();
        $this->como('lucia.vega@andesmarket.pe')->get('/conocimiento/KB-002')->assertOk()->assertSee('pool de conexiones');
        $this->assertSame($articulo->vistas + 1, $articulo->fresh()->vistas);

        // Un artículo de AndesMarket no existe para otra organización
        $this->como('luis.ramirez@novatech.pe')->get('/conocimiento/KB-007')->assertNotFound();
    }
}
