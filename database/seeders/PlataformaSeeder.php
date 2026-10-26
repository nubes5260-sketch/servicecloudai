<?php

namespace Database\Seeders;

use App\Enums\HorarioServicio;
use App\Enums\Rol as RolCodigo;
use App\Models\Permiso;
use App\Models\PlanSaas;
use App\Models\PoliticaSla;
use App\Models\Rol;
use Illuminate\Database\Seeder;

/**
 * Datos de plataforma (no dependen de una organización):
 * permisos por rol, planes y plantillas SLA globales.
 * Los roles y planes base ya los inserta el esquema SQL.
 */
class PlataformaSeeder extends Seeder
{
    /** Plantillas SLA: [respuesta, resolución] en minutos por prioridad. */
    public const PLANTILLAS_SLA = [
        'Gold' => [
            'descripcion' => 'Cobertura 24x7 para servicios críticos.',
            'horario' => HorarioServicio::Continuo,
            'reglas' => ['critica' => [15, 120], 'alta' => [30, 240], 'media' => [120, 480], 'baja' => [240, 1440]],
        ],
        'Silver' => [
            'descripcion' => 'Cobertura 24x7 con tiempos estándar.',
            'horario' => HorarioServicio::Continuo,
            'reglas' => ['critica' => [30, 240], 'alta' => [60, 480], 'media' => [240, 960], 'baja' => [480, 2880]],
        ],
        'Bronze' => [
            'descripcion' => 'Horario laboral L-V 08:00-18:00.',
            'horario' => HorarioServicio::Laboral,
            'reglas' => ['critica' => [60, 480], 'alta' => [120, 960], 'media' => [480, 1920], 'baja' => [960, 4800]],
        ],
    ];

    public function run(): void
    {
        $this->planes();
        $this->permisos();
        $this->plantillasSla();
    }

    private function planes(): void
    {
        $planes = [
            'Starter' => [25, 100, 500, ['Mesa de ayuda', 'Base de conocimiento', 'SLA estándar']],
            'Business' => [150, 1000, 5000, ['Todo Starter', 'SLA personalizados', 'Reportes CSV', 'Asignación automática']],
            'Enterprise' => [null, null, null, ['Todo Business', 'Usuarios y activos ilimitados', 'API REST', 'Soporte 24x7']],
        ];
        foreach ($planes as $nombre => [$usuarios, $activos, $tickets, $caracteristicas]) {
            PlanSaas::where('nombre', $nombre)->update([
                'limite_usuarios' => $usuarios,
                'limite_activos' => $activos,
                'limite_tickets' => $tickets,
                'caracteristicas' => json_encode($caracteristicas, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    private function permisos(): void
    {
        $a = RolCodigo::AdminSaas->value;
        $o = RolCodigo::AdminOrganizacion->value;
        $s = RolCodigo::Supervisor->value;
        $t = RolCodigo::Tecnico->value;
        $u = RolCodigo::Solicitante->value;

        // Matriz de permisos por rol (referencial; las Policies la aplican).
        $matriz = [
            'dashboard.ver' => ['Ver el dashboard', [$a, $o, $s, $t, $u]],
            'organizaciones.gestionar' => ['Crear, editar y eliminar organizaciones', [$a]],
            'organizaciones.ver' => ['Ver la organización propia', [$a, $o]],
            'usuarios.gestionar' => ['Administrar usuarios', [$a, $o]],
            'usuarios.ver' => ['Ver usuarios', [$a, $o, $s]],
            'maestros.gestionar' => ['Administrar activos, servicios y contratos', [$a, $o]],
            'maestros.ver' => ['Ver activos, servicios y contratos', [$a, $o, $s, $t]],
            'tickets.ver_todos' => ['Ver todos los tickets de la organización', [$a, $o, $s]],
            'tickets.ver_asignados' => ['Ver tickets asignados', [$t]],
            'tickets.ver_propios' => ['Ver tickets propios', [$u]],
            'tickets.crear' => ['Registrar tickets', [$a, $o, $s, $t, $u]],
            'tickets.gestionar' => ['Cambiar estado, prioridad y técnico', [$a, $o, $s, $t]],
            'incidencias.gestionar' => ['Administrar incidencias y problemas', [$a, $o, $s]],
            'incidencias.actualizar' => ['Actualizar incidencias asignadas', [$t]],
            'solicitudes.crear' => ['Crear solicitudes', [$a, $o, $s, $u]],
            'solicitudes.aprobar' => ['Aprobar o rechazar solicitudes', [$a, $o, $s]],
            'solicitudes.atender' => ['Atender solicitudes aprobadas', [$a, $o, $t]],
            'sla.gestionar' => ['Administrar políticas y reglas SLA', [$a, $o]],
            'sla.ver' => ['Ver SLA', [$a, $o, $s]],
            'asignaciones.mover' => ['Mover tarjetas del kanban', [$a, $o, $s, $t]],
            'asignaciones.asignar' => ['Asignar técnicos', [$a, $o, $s]],
            'conocimiento.gestionar' => ['Administrar artículos de conocimiento', [$a, $o, $s]],
            'conocimiento.borrador' => ['Crear borradores de artículos', [$t]],
            'conocimiento.ver' => ['Ver artículos publicados', [$a, $o, $s, $t, $u]],
            'reportes.ver' => ['Ver y descargar reportes', [$a, $o, $s]],
            'configuracion.plataforma' => ['Configurar la plataforma', [$a]],
            'configuracion.organizacion' => ['Configurar la organización', [$a, $o]],
            'configuracion.preferencias' => ['Preferencias personales', [$a, $o, $s, $t, $u]],
        ];

        $roles = Rol::pluck('id', 'codigo');
        foreach ($matriz as $codigo => [$descripcion, $codigosRol]) {
            $permiso = Permiso::updateOrCreate(['codigo' => $codigo], ['descripcion' => $descripcion]);
            $permiso->roles()->sync($roles->only($codigosRol)->values()->all());
        }
    }

    private function plantillasSla(): void
    {
        foreach (self::PLANTILLAS_SLA as $nombre => $plantilla) {
            $politica = PoliticaSla::firstOrCreate(
                ['organizacion_id' => null, 'nombre' => $nombre],
                ['descripcion' => $plantilla['descripcion'].' (plantilla)', 'activo' => true],
            );
            self::crearReglas($politica, $plantilla);
        }
    }

    /** Crea las 4 reglas de una política a partir de una plantilla. */
    public static function crearReglas(PoliticaSla $politica, array $plantilla): void
    {
        foreach ($plantilla['reglas'] as $prioridad => [$respuesta, $resolucion]) {
            $politica->reglas()->updateOrCreate(
                ['prioridad' => $prioridad],
                [
                    'respuesta_minutos' => $respuesta,
                    'resolucion_minutos' => $resolucion,
                    'horario_servicio' => $plantilla['horario'],
                ],
            );
        }
    }
}
