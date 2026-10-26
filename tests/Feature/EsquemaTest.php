<?php

namespace Tests\Feature;

use App\Models\Organizacion;
use App\Models\Ticket;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Esquema normalizado v2 (docs/BASE-DE-DATOS.md). */
class EsquemaTest extends TestCase
{
    use RefreshDatabase;

    private const TABLAS_LARAVEL = ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'personal_access_tokens'];

    public function test_el_esquema_crea_las_46_tablas(): void
    {
        $tablas = collect(DB::select("select table_name from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE'"))
            ->pluck('table_name')
            ->diff(self::TABLAS_LARAVEL);

        $this->assertCount(46, $tablas);
        foreach (['tickets', 'usuarios_roles', 'categorias_tickets', 'categorias_servicio', 'categorias_conocimiento', 'servicios_activos', 'fragmentos_rag'] as $tabla) {
            $this->assertContains($tabla, $tablas);
        }
        $this->assertNotContains('usuarios_roles_organizacion', $tablas); // la organización ya está en usuarios
    }

    public function test_no_quedan_columnas_duplicadas(): void
    {
        $this->assertFalse(Schema::hasColumn('activos', 'servicio_id'));             // solo servicios_activos
        $this->assertFalse(Schema::hasColumns('tickets', ['categoria']));            // solo categoria_id
        $this->assertFalse(Schema::hasColumn('tickets', 'subcategoria'));            // jerarquía padre_id
        $this->assertFalse(Schema::hasColumn('tickets', 'politica_sla_id'));         // se deriva de tickets_sla
        $this->assertFalse(Schema::hasColumn('servicios', 'categoria'));             // catálogo categorias_servicio
        $this->assertFalse(Schema::hasColumn('articulos_conocimiento', 'categoria')); // catálogo categorias_conocimiento
    }

    public function test_el_esquema_siembra_roles_planes_y_categorias_de_conocimiento(): void
    {
        $this->assertSame(
            ['ADMIN_ORGANIZACION', 'ADMIN_SAAS', 'SOLICITANTE', 'SUPERVISOR', 'TECNICO'],
            DB::table('roles')->orderBy('codigo')->pluck('codigo')->all(),
        );
        $this->assertSame(3, DB::table('planes_saas')->count());
        $this->assertSame(9, DB::table('categorias_conocimiento')->count());
    }

    public function test_la_tabla_users_de_laravel_no_existe(): void
    {
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('users'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('sessions'));
    }

    public function test_el_rol_respeta_el_ambito_del_usuario(): void
    {
        $tecnico = Usuario::sinFiltroOrganizacion()->where('email', 'maria.pena@andesmarket.pe')->firstOrFail();
        $adminSaas = DB::table('roles')->where('codigo', 'ADMIN_SAAS')->value('id');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('solo puede asignarse a usuarios de la plataforma');
        DB::table('usuarios_roles')->insert(['usuario_id' => $tecnico->id, 'rol_id' => $adminSaas]);
    }

    public function test_la_jerarquia_de_categorias_no_admite_ciclos(): void
    {
        $org = Organizacion::where('codigo', '!=', null)->firstOrFail()->id;
        $raiz = DB::table('categorias_tickets')->insertGetId(['organizacion_id' => $org, 'nombre' => 'Raíz prueba']);
        $hija = DB::table('categorias_tickets')->insertGetId(['organizacion_id' => $org, 'nombre' => 'Hija prueba', 'padre_id' => $raiz]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('ciclo en padre_id');
        DB::table('categorias_tickets')->where('id', $raiz)->update(['padre_id' => $hija]);
    }

    public function test_las_tablas_puente_no_mezclan_organizaciones(): void
    {
        $ticketAndes = Ticket::sinFiltroOrganizacion()->where('codigo', 'TKT-2026-00010')->firstOrFail();
        $problemaNovatech = DB::table('problemas')->where('codigo', 'PRB-002')->first();

        $this->expectException(QueryException::class);
        DB::table('problemas_tickets')->insert([
            'organizacion_id' => $problemaNovatech->organizacion_id, 'problema_id' => $problemaNovatech->id, 'ticket_id' => $ticketAndes->id,
        ]);
    }

    public function test_la_incidencia_vincula_su_ticket_al_problema(): void
    {
        // INC-0001 (TKT-2026-00010, PRB-001): el trigger garantiza el vínculo ticket ↔ problema
        $incidencia = DB::table('incidencias')->where('codigo', 'INC-0001')->first();
        DB::table('problemas_tickets')->where('problema_id', $incidencia->problema_id)->where('ticket_id', $incidencia->ticket_id)->delete();

        DB::table('incidencias')->where('id', $incidencia->id)->update(['problema_id' => null]);
        DB::table('incidencias')->where('id', $incidencia->id)->update(['problema_id' => $incidencia->problema_id]);

        $this->assertTrue(DB::table('problemas_tickets')->where('problema_id', $incidencia->problema_id)->where('ticket_id', $incidencia->ticket_id)->exists());
    }
}
