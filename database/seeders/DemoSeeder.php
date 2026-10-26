<?php

namespace Database\Seeders;

use App\Enums\EstadoTicket;
use App\Enums\Impacto;
use App\Enums\Prioridad;
use App\Enums\Rol as RolCodigo;
use App\Enums\Urgencia;
use App\Models\Activo;
use App\Models\CategoriaServicio;
use App\Models\CategoriaTicket;
use App\Models\ConfiguracionOrganizacion;
use App\Models\Contrato;
use App\Models\EquipoSoporte;
use App\Models\Incidencia;
use App\Models\Notificacion;
use App\Models\Organizacion;
use App\Models\PlanSaas;
use App\Models\PoliticaSla;
use App\Models\PreferenciaNotificacion;
use App\Models\Problema;
use App\Models\Rol;
use App\Models\Servicio;
use App\Models\Solicitud;
use App\Models\Ticket;
use App\Models\TicketAsignacion;
use App\Models\TicketComentario;
use App\Models\TicketHistorial;
use App\Models\TicketSla;
use App\Models\Usuario;
use App\Services\SlaService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * DATOS SINTÉTICOS de demostración y prueba. Todo es ficticio y coherente con el dominio ITSM:
 * 3 organizaciones, 12 usuarios, 5 servicios, 5 activos y 12 tickets escritos a mano
 * (sin datos aleatorios), con fechas relativas a "ahora" para que el SLA se vea vigente.
 * Contraseña de todos los usuarios: password (solo para demo; cambiarla en un despliegue real).
 */
class DemoSeeder extends Seeder
{
    /** @var array<string, Organizacion> clave corta => organización */
    private array $orgs = [];

    /** @var array<string, Usuario> email => usuario */
    private array $usuarios = [];

    /** @var array<string, Servicio> nombre => servicio */
    private array $servicios = [];

    /** @var array<string, Activo> nombre => activo */
    private array $activos = [];

    /** @var array<string, PoliticaSla> "org:nombre" => política */
    private array $politicas = [];

    /** @var array<string, EquipoSoporte> org => equipo "Mesa de Ayuda" */
    private array $equipos = [];

    private CarbonImmutable $ahora;

    private SlaService $sla;

    public function run(SlaService $sla): void
    {
        $this->sla = $sla;
        $this->ahora = CarbonImmutable::now()->startOfMinute();

        $this->organizaciones();
        $this->usuarios();
        $this->equipos();
        $this->categorias();
        $this->politicasSla();
        $this->contratosServiciosActivos();

        $this->call(ConocimientoSeeder::class);

        $this->tickets();
        $this->incidenciasProblemasSolicitudes();
        $this->notificaciones();
    }

    // ---------------------------------------------------------------------
    // Organizaciones y usuarios
    // ---------------------------------------------------------------------

    private function organizaciones(): void
    {
        $planes = PlanSaas::pluck('id', 'nombre');
        $datos = [
            // clave => código, razón social, RUC (ficticio), sector, plan, estado, correo, teléfono, dirección
            'andes' => ['ORG-001', 'AndesMarket S.A.C.', '20000000011', 'Retail', 'Enterprise', 'activa', 'ti@andesmarket.pe', '+51 1 400 1000', 'Av. Principal 100, Lima'],
            'novatech' => ['ORG-002', 'Grupo NovaTech', '20000000022', 'Tecnología', 'Business', 'activa', 'mesa@novatech.pe', '+51 1 400 2000', 'Calle Los Pinos 200, San Isidro'],
            'horizonte' => ['ORG-003', 'Instituto Horizonte', '20000000033', 'Educación', 'Starter', 'evaluacion', 'ti@horizonte.edu.pe', '+51 43 400 300', 'Av. España 300, Chimbote'],
        ];

        foreach ($datos as $clave => [$codigo, $razon, $ruc, $sector, $plan, $estado, $email, $telefono, $direccion]) {
            $this->orgs[$clave] = Organizacion::create([
                'codigo' => $codigo, 'razon_social' => $razon, 'ruc' => $ruc, 'sector' => $sector,
                'plan_id' => $planes[$plan], 'estado' => $estado, 'email' => $email,
                'telefono' => $telefono, 'direccion' => $direccion, 'creado_en' => $this->ahora->subMonths(6),
            ]);
            ConfiguracionOrganizacion::create([
                'organizacion_id' => $this->orgs[$clave]->id,
                'zona_horaria' => 'America/Lima',
            ]);
        }
    }

    private function usuarios(): void
    {
        $password = Hash::make('password');
        $roles = Rol::pluck('id', 'codigo');

        $datos = [
            // correo, nombre, organización, rol, cargo, estado
            ['admin@servicecloud.ai', 'Jorge Espinoza', null, RolCodigo::AdminSaas, 'Administrador de plataforma', 'activo'],

            ['rosa.villanueva@andesmarket.pe', 'Rosa Villanueva', 'andes', RolCodigo::AdminOrganizacion, 'Jefa de TI', 'activo'],
            ['ana.torres@andesmarket.pe', 'Ana Torres', 'andes', RolCodigo::Supervisor, 'Supervisora de mesa de ayuda', 'activo'],
            ['maria.pena@andesmarket.pe', 'María Peña', 'andes', RolCodigo::Tecnico, 'Técnica de aplicaciones', 'activo'],
            ['kevin.rojas@andesmarket.pe', 'Kevin Rojas', 'andes', RolCodigo::Tecnico, 'Técnico de infraestructura', 'activo'],
            ['lucia.vega@andesmarket.pe', 'Lucía Vega', 'andes', RolCodigo::Solicitante, 'Analista comercial', 'activo'],
            ['daniela.paredes@andesmarket.pe', 'Daniela Paredes', 'andes', RolCodigo::Solicitante, 'Asistente de finanzas', 'activo'],

            ['patricia.gomez@novatech.pe', 'Patricia Gómez', 'novatech', RolCodigo::AdminOrganizacion, 'Gerente de TI', 'activo'],
            ['luis.ramirez@novatech.pe', 'Luis Ramírez', 'novatech', RolCodigo::Tecnico, 'Técnico de soporte', 'activo'],
            ['marco.rios@novatech.pe', 'Marco Ríos', 'novatech', RolCodigo::Solicitante, 'Desarrollador', 'activo'],

            ['valeria.nunez@horizonte.edu.pe', 'Valeria Núñez', 'horizonte', RolCodigo::AdminOrganizacion, 'Directora de TI', 'activo'],
            ['diego.campos@horizonte.edu.pe', 'Diego Campos', 'horizonte', RolCodigo::Tecnico, 'Técnico de soporte', 'inactivo'],
        ];

        foreach ($datos as $i => [$email, $nombre, $org, $rol, $cargo, $estado]) {
            $usuario = Usuario::create([
                'organizacion_id' => $org ? $this->orgs[$org]->id : null,
                'nombre' => $nombre, 'email' => $email, 'password_hash' => $password,
                'telefono' => sprintf('+51 900 000 %03d', $i + 1), 'cargo' => $cargo, 'estado' => $estado,
                'ultimo_acceso' => $estado === 'activo' ? $this->ahora->subHours($i + 1) : null,
            ]);
            $usuario->roles()->attach($roles[$rol->value]);
            PreferenciaNotificacion::create(['usuario_id' => $usuario->id]);
            $this->usuarios[$email] = $usuario;
        }
    }

    private function u(string $email): Usuario
    {
        return $this->usuarios[$email];
    }

    /** Un equipo "Mesa de Ayuda" por organización con técnicos. */
    private function equipos(): void
    {
        $miembros = ['andes' => ['maria.pena@andesmarket.pe', 'kevin.rojas@andesmarket.pe'], 'novatech' => ['luis.ramirez@novatech.pe']];
        foreach ($miembros as $org => $correos) {
            $equipo = EquipoSoporte::create(['organizacion_id' => $this->orgs[$org]->id, 'nombre' => 'Mesa de Ayuda', 'descripcion' => 'Primer nivel de atención']);
            $equipo->miembros()->attach(array_fill_keys(array_map(fn ($e) => $this->u($e)->id, $correos), ['organizacion_id' => $equipo->organizacion_id]));
            $this->equipos[$org] = $equipo;
        }
    }

    private function categorias(): void
    {
        foreach ($this->orgs as $org) {
            foreach (['Aplicaciones', 'Redes', 'Accesos', 'Software', 'Hardware', 'Infraestructura'] as $nombre) {
                CategoriaTicket::create(['organizacion_id' => $org->id, 'nombre' => $nombre]);
            }
        }
    }

    private function categoriaId(string $org, string $nombre): ?int
    {
        return CategoriaTicket::where('organizacion_id', $this->orgs[$org]->id)->where('nombre', $nombre)->value('id');
    }

    // ---------------------------------------------------------------------
    // SLA, contratos, servicios y activos
    // ---------------------------------------------------------------------

    /** Cada organización copia sus plantillas (las FK compuestas no permiten usar las globales). */
    private function politicasSla(): void
    {
        foreach (['andes' => ['Gold', 'Silver'], 'novatech' => ['Silver'], 'horizonte' => ['Bronze']] as $org => $nombres) {
            foreach ($nombres as $nombre) {
                $plantilla = PlataformaSeeder::PLANTILLAS_SLA[$nombre];
                $politica = PoliticaSla::create(['organizacion_id' => $this->orgs[$org]->id, 'nombre' => $nombre, 'descripcion' => $plantilla['descripcion']]);
                PlataformaSeeder::crearReglas($politica, $plantilla);
                $this->politicas["$org:$nombre"] = $politica;
            }
            ConfiguracionOrganizacion::where('organizacion_id', $this->orgs[$org]->id)
                ->update(['configuracion' => json_encode(['politica_sla_defecto_id' => $this->politicas["$org:{$nombres[0]}"]->id])]);
        }
    }

    private function contratosServiciosActivos(): void
    {
        $contratos = [
            // org, código, nombre, inicio, fin, monto, estado, SLA
            ['andes', 'CTR-001', 'Soporte TI 2026', '2026-01-01', '2026-12-31', 60000, 'vigente', 'Gold'],
            ['novatech', 'CTR-002', 'Mesa de ayuda Business', '2026-03-01', '2027-02-28', 36000, 'vigente', 'Silver'],
            ['horizonte', 'CTR-003', 'Piloto de mesa de ayuda', '2026-12-01', '2027-03-31', 6000, 'borrador', 'Bronze'],
        ];
        $idsContrato = [];
        foreach ($contratos as [$org, $codigo, $nombre, $inicio, $fin, $monto, $estado, $sla]) {
            $idsContrato[$org] = Contrato::create([
                'organizacion_id' => $this->orgs[$org]->id, 'codigo' => $codigo, 'nombre' => $nombre,
                'fecha_inicio' => $inicio, 'fecha_fin' => $fin, 'monto' => $monto, 'moneda' => 'PEN', 'estado' => $estado,
                'politica_sla_id' => $this->politicas["$org:$sla"]->id, 'descripcion' => "Soporte TI con SLA $sla.",
            ])->id;
        }

        $servicios = [
            // org, código, nombre, categoría, responsable, disponibilidad, estado, SLA
            ['andes', 'SRV-01', 'ERP Corporativo', 'Aplicaciones', 'maria.pena@andesmarket.pe', 99.9, 'operativo', 'Gold'],
            ['andes', 'SRV-02', 'Base de datos', 'Infraestructura', 'kevin.rojas@andesmarket.pe', 99.9, 'operativo', 'Gold'],
            ['andes', 'SRV-03', 'Workplace', 'Software', 'kevin.rojas@andesmarket.pe', 99.5, 'operativo', 'Silver'],
            ['novatech', 'SRV-04', 'CRM Cloud', 'Aplicaciones', 'luis.ramirez@novatech.pe', 99.8, 'degradado', 'Silver'],
            ['novatech', 'SRV-05', 'VPN Corporativa', 'Redes', 'luis.ramirez@novatech.pe', 99.9, 'operativo', 'Silver'],
        ];
        foreach ($servicios as [$org, $codigo, $nombre, $categoria, $responsable, $disponibilidad, $estado, $sla]) {
            $orgId = $this->orgs[$org]->id;
            $this->servicios[$nombre] = Servicio::create([
                'organizacion_id' => $orgId, 'codigo' => $codigo, 'nombre' => $nombre, 'descripcion' => "Servicio $nombre.",
                'categoria_id' => CategoriaServicio::idPara($orgId, $categoria), 'responsable_id' => $this->u($responsable)->id,
                'contrato_id' => $idsContrato[$org], 'politica_sla_id' => $this->politicas["$org:$sla"]->id,
                'disponibilidad_objetivo' => $disponibilidad, 'estado' => $estado,
            ]);
        }

        $activos = [
            // org, código, nombre, tipo, IP, estado, responsable, servicios, fabricante, modelo
            ['andes', 'AST-0001', 'DB-PROD-01', 'servidor', '10.0.0.11', 'operativo', 'kevin.rojas@andesmarket.pe', ['ERP Corporativo', 'Base de datos'], 'Dell', 'PowerEdge R750'],
            ['andes', 'AST-0002', 'APP-ERP-01', 'vm_cloud', '10.0.0.20', 'operativo', 'maria.pena@andesmarket.pe', ['ERP Corporativo'], 'Microsoft', 'Azure D4s v5'],
            ['andes', 'AST-0003', 'NB-MKT-023', 'laptop', 'DHCP', 'asignado', 'lucia.vega@andesmarket.pe', ['Workplace'], 'Lenovo', 'ThinkPad T14'],
            ['andes', 'AST-0004', 'PRN-ADM-01', 'impresora', '10.0.0.40', 'mantenimiento', 'kevin.rojas@andesmarket.pe', ['Workplace'], 'HP', 'LaserJet M507'],
            ['novatech', 'AST-0005', 'APP-CRM-02', 'vm_cloud', '10.1.0.14', 'degradado', 'luis.ramirez@novatech.pe', ['CRM Cloud'], 'AWS', 'm6i.xlarge'],
        ];
        foreach ($activos as [$org, $codigo, $nombre, $tipo, $ip, $estado, $responsable, $servicios, $fabricante, $modelo]) {
            $orgId = $this->orgs[$org]->id;
            $activo = Activo::create([
                'organizacion_id' => $orgId, 'codigo' => $codigo, 'nombre' => $nombre, 'tipo' => $tipo,
                'numero_serie' => 'SN-'.substr($codigo, 4), 'fabricante' => $fabricante, 'modelo' => $modelo,
                'ip' => $ip, 'hostname' => strtolower($nombre), 'ubicacion' => $tipo === 'vm_cloud' ? 'Nube' : 'Sede principal',
                'responsable_id' => $this->u($responsable)->id, 'estado' => $estado,
                'proveedor_cloud' => $tipo === 'vm_cloud' ? $fabricante : null,
            ]);
            $activo->servicios()->attach(array_fill_keys(array_map(fn ($s) => $this->servicios[$s]->id, $servicios), ['organizacion_id' => $orgId]));
            $this->activos[$nombre] = $activo;
        }
    }

    // ---------------------------------------------------------------------
    // Tickets
    // ---------------------------------------------------------------------

    /** 12 tickets, uno por situación típica del flujo (del más antiguo al más reciente). */
    private function tickets(): void
    {
        $dia = 1440;
        $tickets = [
            // código, org, título, categoría, servicio, tipo, impacto, urgencia, estado, técnico, solicitante, hace (min), extra
            ['00001', 'andes', 'Restablecer la contraseña del correo', 'Accesos', 'Workplace', 'solicitud', 'bajo', 'alta', 'cerrado', 'maria.pena', 'lucia.vega', 6 * $dia, []],
            ['00002', 'andes', 'Error al generar el reporte de ventas en el ERP', 'Aplicaciones', 'ERP Corporativo', 'incidente', 'medio', 'media', 'cerrado', 'maria.pena', 'daniela.paredes', 4 * $dia, ['factor_resolucion' => 1.4]], // resuelto fuera de SLA
            ['00003', 'novatech', 'Instalar la VPN en una laptop nueva', 'Software', 'VPN Corporativa', 'solicitud', 'bajo', 'media', 'cerrado', 'luis.ramirez', 'marco.rios', 3 * $dia, []],
            ['00004', 'andes', 'El Wi-Fi de invitados no asigna IP', 'Redes', 'Workplace', 'incidente', 'bajo', 'media', 'cerrado', 'kevin.rojas', 'lucia.vega', 2 * $dia, []],
            ['00005', 'andes', 'Instalar el software contable en la laptop de finanzas', 'Software', 'Workplace', 'solicitud', 'bajo', 'baja', 'resuelto', 'maria.pena', 'daniela.paredes', 300, ['solucion' => 'Software instalado y licenciado. Falta que el usuario confirme.']],
            ['00006', 'andes', 'La impresora del área administrativa atasca papel', 'Hardware', 'Workplace', 'incidente', 'bajo', 'media', 'en_espera', 'kevin.rojas', 'daniela.paredes', $dia, ['activo' => 'PRN-ADM-01']],
            ['00007', 'novatech', 'Alta latencia en el CRM durante el cierre comercial', 'Aplicaciones', 'CRM Cloud', 'incidente', 'medio', 'alta', 'en_progreso', 'luis.ramirez', 'marco.rios', 87, ['activo' => 'APP-CRM-02']],
            ['00008', 'andes', 'Alerta de espacio en disco del servidor DB-PROD-01', 'Infraestructura', 'Base de datos', 'incidente', 'alto', 'media', 'abierto', null, 'ana.torres', 129, ['activo' => 'DB-PROD-01', 'origen' => 'monitorizacion']],
            ['00009', 'novatech', 'La VPN se desconecta al iniciar sesión remota', 'Redes', 'VPN Corporativa', 'incidente', 'alto', 'alta', 'asignado', 'luis.ramirez', 'marco.rios', 99, []],
            ['00010', 'andes', 'La aplicación demora demasiado y finalmente muestra error al consultar clientes.', 'Aplicaciones', 'ERP Corporativo', 'incidente', 'alto', 'media', 'en_progreso', 'maria.pena', 'lucia.vega', 168, ['activo' => 'APP-ERP-01', 'factor_respuesta' => 0.4,
                'descripcion' => 'El módulo de clientes del ERP tarda más de 30 segundos en responder y luego muestra "Error al consultar clientes". Pasa en horas de carga y afecta a todo el equipo comercial.']],
            ['00011', 'andes', 'OneDrive no sincroniza la carpeta de reportes', 'Software', 'Workplace', 'incidente', 'medio', 'media', 'abierto', null, 'daniela.paredes', 25, []],
            ['00012', 'andes', 'Permiso para la carpeta de Finanzas', 'Accesos', 'Workplace', 'solicitud', 'medio', 'media', 'asignado', 'kevin.rojas', 'lucia.vega', 15, []],
        ];
        $dominio = ['andes' => 'andesmarket.pe', 'novatech' => 'novatech.pe'];

        foreach ($tickets as [$numero, $org, $titulo, $categoria, $servicio, $tipo, $impacto, $urgencia, $estado, $tecnico, $solicitante, $haceMin, $extra]) {
            $ticket = $this->crearTicket($extra + [
                'org' => $org, 'codigo' => "TKT-2026-$numero", 'titulo' => $titulo, 'categoria' => $categoria, 'servicio' => $servicio,
                'tipo' => $tipo, 'impacto' => $impacto, 'urgencia' => $urgencia, 'estado' => $estado,
                'tecnico' => $tecnico ? "$tecnico@{$dominio[$org]}" : null, 'solicitante' => "$solicitante@{$dominio[$org]}",
                'creado' => $this->ahora->subMinutes($haceMin),
            ]);

            if ($numero === '00010') {
                // Caso de ejemplo del enunciado: conversación entre el usuario y la técnica
                TicketComentario::create(['ticket_id' => $ticket->id, 'autor_id' => $this->u('ana.torres@andesmarket.pe')->id, 'contenido' => 'El error aparece al consultar clientes con más de un año de historial.', 'es_interno' => false, 'creado_en' => $this->ahora->subMinutes(120)]);
                TicketComentario::create(['ticket_id' => $ticket->id, 'autor_id' => $this->u('maria.pena@andesmarket.pe')->id, 'contenido' => 'Revisando APP-ERP-01: el pool de conexiones está al 92 %.', 'es_interno' => true, 'creado_en' => $this->ahora->subMinutes(95)]);
            }
        }
    }

    /**
     * Crea un ticket con SLA, historial, asignación y fechas coherentes.
     *
     * @param  array<string, mixed>  $d
     */
    private function crearTicket(array $d): Ticket
    {
        $org = $d['org'];
        $creado = $d['creado'];
        $estado = EstadoTicket::from($d['estado']);
        $impacto = Impacto::from($d['impacto']);
        $urgencia = Urgencia::from($d['urgencia']);
        $prioridad = Prioridad::desdeMatriz($impacto, $urgencia);
        $servicio = $this->servicios[$d['servicio']];
        $regla = $servicio->politicaSla->reglas()->where('prioridad', $prioridad->value)->first();
        $tecnico = $d['tecnico'] ? $this->u($d['tecnico']) : null;
        $solicitante = $this->u($d['solicitante']);

        $vencRespuesta = $this->sla->sumarMinutos($creado, $regla->respuesta_minutos, $regla->horario_servicio);
        $vencResolucion = $this->sla->sumarMinutos($creado, $regla->resolucion_minutos, $regla->horario_servicio);
        $atendido = ! in_array($estado, [EstadoTicket::Abierto, EstadoTicket::Asignado, EstadoTicket::Cancelado], true);
        $primeraRespuesta = $atendido ? $creado->addMinutes(max(3, (int) ($regla->respuesta_minutos * ($d['factor_respuesta'] ?? 0.5))))->min($this->ahora->subMinute()) : null;
        $resuelto = in_array($estado, [EstadoTicket::Resuelto, EstadoTicket::Cerrado], true)
            ? $creado->addMinutes((int) ($regla->resolucion_minutos * ($d['factor_resolucion'] ?? 0.6)))->min($this->ahora->subMinutes(5))
            : null;
        $cerrado = $estado === EstadoTicket::Cerrado ? $resuelto->addHours(2)->min($this->ahora) : null;

        $ticket = Ticket::create([
            'organizacion_id' => $this->orgs[$org]->id, 'codigo' => $d['codigo'], 'titulo' => $d['titulo'],
            'descripcion' => $d['descripcion'] ?? $d['titulo'].'.', 'tipo' => $d['tipo'],
            'categoria_id' => $this->categoriaId($org, $d['categoria']),
            'prioridad' => $prioridad, 'impacto' => $impacto, 'urgencia' => $urgencia, 'estado' => $estado,
            'origen' => $d['origen'] ?? 'portal', 'solicitante_id' => $solicitante->id, 'tecnico_id' => $tecnico?->id,
            'equipo_id' => $tecnico ? $this->equipos[$org]->id : null, 'servicio_id' => $servicio->id,
            'activo_id' => isset($d['activo']) ? $this->activos[$d['activo']]->id : null,
            'primera_respuesta_en' => $primeraRespuesta, 'vencimiento_respuesta' => $vencRespuesta, 'vencimiento_resolucion' => $vencResolucion,
            'resuelto_en' => $resuelto, 'cerrado_en' => $cerrado,
            'solucion' => $resuelto ? ($d['solucion'] ?? 'Se aplicó el procedimiento de la base de conocimiento y el usuario confirmó la solución.') : null,
            'creado_en' => $creado, 'actualizado_en' => $cerrado ?? $resuelto ?? $primeraRespuesta ?? $creado,
        ]);

        $pausados = $estado === EstadoTicket::EnEspera ? 45 : 0;
        TicketSla::create([
            'ticket_id' => $ticket->id, 'regla_sla_id' => $regla->id,
            'respuesta_cumplida' => $primeraRespuesta ? $primeraRespuesta <= $vencRespuesta : ($this->ahora > $vencRespuesta ? false : null),
            'resolucion_cumplida' => $resuelto ? $resuelto <= $vencResolucion : ($this->ahora > $vencResolucion ? false : null),
            'minutos_pausados' => $pausados, 'ultima_pausa_en' => $pausados ? $this->ahora->subMinutes($pausados) : null,
            'calculado_en' => $this->ahora,
        ]);

        // Historial: creación → asignación → cada estado por el que pasó
        $historial = fn (?Usuario $u, string $campo, ?string $antes, ?string $despues, $fecha) => TicketHistorial::create([
            'ticket_id' => $ticket->id, 'usuario_id' => $u?->id, 'campo' => $campo, 'valor_anterior' => $antes, 'valor_nuevo' => $despues, 'fecha' => $fecha,
        ]);
        $historial($solicitante, 'creacion', null, 'Ticket creado desde '.$ticket->origen->label(), $creado);
        if ($tecnico) {
            $supervisora = $this->usuarios[$org === 'andes' ? 'ana.torres@andesmarket.pe' : 'patricia.gomez@novatech.pe'];
            $asignado = $creado->addMinutes(2);
            TicketAsignacion::create(['ticket_id' => $ticket->id, 'tecnico_nuevo_id' => $tecnico->id, 'equipo_id' => $ticket->equipo_id, 'asignado_por_id' => $supervisora->id, 'motivo' => 'Asignación inicial', 'fecha' => $asignado]);
            $historial($supervisora, 'tecnico', null, $tecnico->nombre, $asignado);
            $historial($supervisora, 'estado', 'abierto', 'asignado', $asignado);
            $anterior = 'asignado';
            foreach (['en_progreso' => $primeraRespuesta, 'en_espera' => $estado === EstadoTicket::EnEspera ? $primeraRespuesta?->addMinutes(20)->min($this->ahora) : null, 'resuelto' => $resuelto, 'cerrado' => $cerrado] as $valor => $fecha) {
                if ($fecha) {
                    $historial($valor === 'cerrado' ? $solicitante : $tecnico, 'estado', $anterior, $valor, $fecha);
                    $anterior = $valor;
                }
            }
        }

        return $ticket;
    }

    // ---------------------------------------------------------------------
    // Incidencias, problemas, solicitudes y notificaciones
    // ---------------------------------------------------------------------

    private function incidenciasProblemasSolicitudes(): void
    {
        $andes = $this->orgs['andes']->id;
        $novatech = $this->orgs['novatech']->id;
        $ticket = fn (string $numero) => Ticket::where('codigo', "TKT-2026-$numero")->value('id');

        $prbErp = Problema::create([
            'organizacion_id' => $andes, 'codigo' => 'PRB-001', 'titulo' => 'Saturación del pool de conexiones del ERP',
            'descripcion' => 'Varios tickets comparten lentitud y timeouts en el ERP Corporativo.', 'causa_raiz' => 'En investigación',
            'servicio_id' => $this->servicios['ERP Corporativo']->id, 'responsable_id' => $this->u('maria.pena@andesmarket.pe')->id,
            'estado' => 'abierto', 'creado_en' => $this->ahora->subDays(2),
        ]);
        $prbVpn = Problema::create([
            'organizacion_id' => $novatech, 'codigo' => 'PRB-002', 'titulo' => 'Cortes de la VPN en horas punta',
            'descripcion' => 'La VPN pierde paquetes cuando muchos usuarios se conectan a la vez.', 'causa_raiz' => 'Firmware desactualizado del router',
            'solucion_temporal' => 'Usar el enlace secundario en horas punta.', 'servicio_id' => $this->servicios['VPN Corporativa']->id,
            'responsable_id' => $this->u('luis.ramirez@novatech.pe')->id, 'estado' => 'con_workaround', 'creado_en' => $this->ahora->subDays(4),
        ]);

        $incidencias = [
            // org, código, ticket, problema, servicio, descripción, impacto, urgencia, responsable, estado, inicio (min), resuelta (min)
            [$andes, 'INC-0001', '00010', $prbErp->id, 'ERP Corporativo', 'Lentitud al consultar clientes en el ERP', 'alto', 'alta', 'maria.pena@andesmarket.pe', 'investigando', 160, null],
            [$novatech, 'INC-0002', '00009', $prbVpn->id, 'VPN Corporativa', 'Cortes del acceso VPN', 'alto', 'media', 'luis.ramirez@novatech.pe', 'en_progreso', 95, null],
            [$andes, 'INC-0003', null, null, 'Workplace', 'Fallas de impresión en el piso 2', 'bajo', 'baja', 'kevin.rojas@andesmarket.pe', 'resuelta', 2 * 1440, 1440],
        ];
        foreach ($incidencias as [$org, $codigo, $tkt, $problema, $servicio, $descripcion, $impacto, $urgencia, $responsable, $estado, $inicio, $resuelta]) {
            Incidencia::create([
                'organizacion_id' => $org, 'codigo' => $codigo, 'ticket_id' => $tkt ? $ticket($tkt) : null, 'problema_id' => $problema,
                'servicio_id' => $this->servicios[$servicio]->id, 'descripcion' => $descripcion, 'impacto' => $impacto, 'urgencia' => $urgencia,
                'prioridad' => Prioridad::desdeMatriz(Impacto::from($impacto), Urgencia::from($urgencia)),
                'responsable_id' => $this->u($responsable)->id, 'estado' => $estado,
                'inicio_en' => $this->ahora->subMinutes($inicio), 'resuelta_en' => $resuelta ? $this->ahora->subMinutes($resuelta) : null,
            ]); // el trigger de la BD vincula ticket ↔ problema en problemas_tickets
        }

        $solicitudes = [
            ['REQ-0001', 'Acceso a Power BI', 'lucia.vega@andesmarket.pe', null, 'media', 'pendiente_aprobacion', 180],
            ['REQ-0002', 'Alta de usuario en el ERP', 'daniela.paredes@andesmarket.pe', 'ana.torres@andesmarket.pe', 'alta', 'completada', 3 * 1440],
        ];
        foreach ($solicitudes as [$codigo, $titulo, $solicitante, $aprobador, $prioridad, $estado, $haceMin]) {
            $creado = $this->ahora->subMinutes($haceMin);
            Solicitud::create([
                'organizacion_id' => $andes, 'codigo' => $codigo, 'titulo' => $titulo, 'descripcion' => "$titulo para mi trabajo diario.",
                'solicitante_id' => $this->u($solicitante)->id, 'aprobador_id' => $aprobador ? $this->u($aprobador)->id : null,
                'prioridad' => $prioridad, 'estado' => $estado, 'aprobada_en' => $aprobador ? $creado->addHours(2) : null,
                'completada_en' => $estado === 'completada' ? $creado->addDay() : null, 'creado_en' => $creado,
            ]);
        }
    }

    private function notificaciones(): void
    {
        $tkt = fn (string $numero) => Ticket::where('codigo', "TKT-2026-$numero")->first();
        $datos = [
            // usuario, ticket, título, mensaje, tipo, hace (min), leída
            ['admin@servicecloud.ai', $tkt('00009'), 'SLA próximo a vencer', 'TKT-2026-00009 vence en 20 minutos.', 'sla', 3, false],
            ['admin@servicecloud.ai', null, 'Incidencia actualizada', 'INC-0002 pasó a En progreso.', 'sistema', 30, true],
            ['ana.torres@andesmarket.pe', $tkt('00008'), 'Nuevo ticket sin asignar', 'TKT-2026-00008: Alerta de espacio en disco del servidor DB-PROD-01', 'ticket', 125, false],
            ['maria.pena@andesmarket.pe', $tkt('00010'), 'Ticket asignado', 'Se te asignó TKT-2026-00010.', 'asignacion', 160, false],
            ['lucia.vega@andesmarket.pe', $tkt('00010'), 'Están atendiendo tu ticket', 'María Peña está trabajando en TKT-2026-00010.', 'ticket', 100, false],
            ['luis.ramirez@novatech.pe', $tkt('00007'), 'Ticket asignado', 'Se te asignó TKT-2026-00007.', 'asignacion', 85, true],
        ];
        foreach ($datos as [$email, $ticket, $titulo, $mensaje, $tipo, $haceMin, $leida]) {
            $usuario = $this->u($email);
            Notificacion::create([
                'organizacion_id' => $usuario->organizacion_id, 'usuario_id' => $usuario->id, 'ticket_id' => $ticket?->id,
                'titulo' => $titulo, 'mensaje' => $mensaje, 'tipo' => $tipo, 'canal' => 'in_app',
                'leida_en' => $leida ? $this->ahora->subMinutes($haceMin - 5) : null,
                'enviada_en' => $this->ahora->subMinutes($haceMin), 'creado_en' => $this->ahora->subMinutes($haceMin),
            ]);
        }
    }
}
