<?php

namespace App\Services;

use App\Enums\EstadoOrganizacion;
use App\Models\CategoriaTicket;
use App\Models\ConfiguracionOrganizacion;
use App\Models\EquipoSoporte;
use App\Models\Organizacion;
use App\Models\PlanSaas;
use App\Models\PoliticaSla;
use App\Support\Codigos;
use Illuminate\Support\Facades\DB;

class OrganizacionService
{
    public const CATEGORIAS_BASE = ['Aplicaciones', 'Redes', 'Accesos', 'Software', 'Infraestructura', 'Hardware', 'Seguridad', 'Bases de datos', 'Cloud'];

    /** Política SLA por defecto según el plan contratado. */
    private const SLA_POR_PLAN = ['Enterprise' => 'Gold', 'Business' => 'Silver', 'Starter' => 'Bronze'];

    /**
     * Alta de una organización lista para operar: configuración, copia de las
     * plantillas SLA globales, categorías de tickets y equipo de Mesa de Ayuda.
     */
    public function crear(array $datos): Organizacion
    {
        return DB::transaction(function () use ($datos) {
            $organizacion = Organizacion::create($datos + ['codigo' => Codigos::siguiente(Organizacion::class, 'ORG', 3)]);

            $defecto = null;
            $planNombre = PlanSaas::whereKey($organizacion->plan_id)->value('nombre');
            $plantillas = PoliticaSla::sinFiltroOrganizacion()->whereNull('organizacion_id')->with('reglas')->get();
            foreach ($plantillas as $plantilla) {
                $copia = PoliticaSla::create([
                    'organizacion_id' => $organizacion->id,
                    'nombre' => $plantilla->nombre,
                    'descripcion' => str_replace(' (plantilla)', '', (string) $plantilla->descripcion),
                    'activo' => true,
                ]);
                foreach ($plantilla->reglas as $regla) {
                    $copia->reglas()->create($regla->only(['prioridad', 'respuesta_minutos', 'resolucion_minutos', 'horario_servicio']));
                }
                if ($plantilla->nombre === (self::SLA_POR_PLAN[$planNombre] ?? 'Silver')) {
                    $defecto = $copia->id;
                }
            }

            ConfiguracionOrganizacion::create([
                'organizacion_id' => $organizacion->id,
                'configuracion' => ['politica_sla_defecto_id' => $defecto],
            ]);

            foreach (self::CATEGORIAS_BASE as $nombre) {
                CategoriaTicket::create(['organizacion_id' => $organizacion->id, 'nombre' => $nombre]);
            }
            EquipoSoporte::create(['organizacion_id' => $organizacion->id, 'nombre' => 'Mesa de Ayuda', 'descripcion' => 'Primer nivel de atención']);

            return $organizacion;
        });
    }

    /**
     * Elimina la organización si no tiene operación registrada; si la tiene,
     * la marca como inactiva (las FK son RESTRICT). Devuelve true si se eliminó.
     */
    public function eliminarODesactivar(Organizacion $organizacion): bool
    {
        $tieneOperacion = $organizacion->usuarios()->withoutGlobalScopes()->exists()
            || $organizacion->tickets()->withoutGlobalScopes()->exists()
            || $organizacion->servicios()->withoutGlobalScopes()->exists()
            || $organizacion->activos()->withoutGlobalScopes()->exists()
            || $organizacion->contratos()->withoutGlobalScopes()->exists();

        if ($tieneOperacion) {
            $organizacion->update(['estado' => EstadoOrganizacion::Inactiva]);

            return false;
        }

        DB::transaction(function () use ($organizacion) {
            PoliticaSla::sinFiltroOrganizacion()->where('organizacion_id', $organizacion->id)->delete();
            CategoriaTicket::sinFiltroOrganizacion()->where('organizacion_id', $organizacion->id)->delete();
            EquipoSoporte::sinFiltroOrganizacion()->where('organizacion_id', $organizacion->id)->delete();
            $organizacion->delete();
        });

        return true;
    }
}
