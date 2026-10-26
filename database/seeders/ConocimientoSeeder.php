<?php

namespace Database\Seeders;

use App\Models\ArticuloConocimiento;
use App\Models\Etiqueta;
use App\Models\Organizacion;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

/**
 * Artículos de la base de conocimiento (los 6 del mockup + ejemplos por organización).
 * El contenido es extenso para que los artículos sirvan como guías reales de solución.
 */
class ConocimientoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Usuario::where('email', 'admin@servicecloud.ai')->first();
        $andes = Organizacion::where('codigo', 'ORG-001')->first();
        $novatech = Organizacion::where('codigo', 'ORG-002')->first();

        foreach ($this->articulos() as $datos) {
            $orgId = match ($datos['org'] ?? null) {
                'andes' => $andes?->id,
                'novatech' => $novatech?->id,
                default => null,
            };
            $autor = isset($datos['autor'])
                ? Usuario::where('email', $datos['autor'])->value('id')
                : $admin?->id;

            $articulo = ArticuloConocimiento::create([
                'organizacion_id' => $orgId,
                'codigo' => $datos['codigo'],
                'categoria' => $datos['categoria'],
                'titulo' => $datos['titulo'],
                'resumen' => $datos['resumen'],
                'contenido' => implode("\n\n", $datos['contenido']),
                'autor_id' => $autor,
                'visibilidad' => $datos['visibilidad'] ?? 'publica',
                'estado' => $datos['estado'] ?? 'publicado',
                'vistas' => $datos['vistas'],
                'version' => $datos['version'] ?? 1,
                'creado_en' => now()->subDays(40),
                'actualizado_en' => now()->subMinutes($datos['actualizado_hace_min']),
            ]);

            $ids = [];
            foreach ($datos['etiquetas'] as $nombre) {
                $ids[] = Etiqueta::firstOrCreate(['nombre' => $nombre])->id;
            }
            $articulo->etiquetas()->sync($ids);
        }
    }

    private function articulos(): array
    {
        return [
            [
                'codigo' => 'KB-001', 'categoria' => 'Bases de datos', 'vistas' => 842, 'actualizado_hace_min' => 2 * 1440,
                'titulo' => 'Optimización de consultas SQL',
                'resumen' => 'Buenas prácticas para identificar consultas lentas, índices faltantes y planes de ejecución costosos.',
                'etiquetas' => ['sql', 'rendimiento', 'base de datos', 'consultas lentas', 'índices'],
                'contenido' => [
                    'Cuando una aplicación demora demasiado al consultar información (por ejemplo, al buscar clientes o pedidos) y finalmente muestra un error de tiempo de espera, la causa más frecuente es una consulta SQL lenta. Antes de aumentar recursos del servidor conviene identificar qué sentencia consume el tiempo y por qué.',
                    'Paso 1 — Identificar la consulta: active el registro de consultas lentas (en PostgreSQL log_min_duration_statement = 1000 ms o la extensión pg_stat_statements) y ordene por tiempo total y tiempo medio. En SQL Server use Query Store; en MySQL, el slow query log. Relacione la hora de los errores reportados por los usuarios con las consultas registradas.',
                    'Paso 2 — Revisar el plan de ejecución: ejecute EXPLAIN ANALYZE sobre la consulta con parámetros reales. Señales de alerta: Seq Scan sobre tablas grandes, estimaciones de filas muy diferentes de las reales, Nested Loop con millones de iteraciones, ordenamientos que se derraman a disco (external merge) y filtros aplicados después de leer toda la tabla.',
                    'Paso 3 — Corregir: cree índices sobre las columnas usadas en WHERE, JOIN y ORDER BY (índices compuestos en el orden correcto), evite funciones sobre columnas indexadas, pagine resultados con LIMIT/keyset en lugar de traer todo el historial, actualice estadísticas (ANALYZE) y elimine el patrón N+1 en el código de la aplicación. Consultas sobre historiales de más de 12 meses suelen requerir índices por fecha o particionamiento.',
                    'Paso 4 — Validar: compare el tiempo antes y después, verifique el impacto en escrituras (cada índice tiene costo) y deje una alerta cuando el tiempo medio supere el umbral acordado. Documente el cambio en el ticket y en el problema asociado si el síntoma era recurrente.',
                ],
            ],
            [
                'codigo' => 'KB-002', 'categoria' => 'Aplicaciones', 'vistas' => 625, 'actualizado_hace_min' => 1440,
                'titulo' => 'Errores por agotamiento de conexiones',
                'resumen' => 'Diagnóstico de connection pool, timeouts y estrategias de recuperación segura.',
                'etiquetas' => ['pool de conexiones', 'timeout', 'aplicaciones', 'base de datos', 'rendimiento'],
                'contenido' => [
                    'El agotamiento del pool de conexiones ocurre cuando todas las conexiones disponibles entre la aplicación y la base de datos están ocupadas y las nuevas peticiones deben esperar. El síntoma típico es lentitud progresiva seguida de errores "connection timeout", "could not obtain connection" o "Database connection timeout", especialmente en horas punta o en consultas pesadas como la consulta de clientes.',
                    'Diagnóstico: revise las métricas del pool (conexiones activas, ociosas y en espera). Un uso sostenido por encima del 80 % o una cola de espera creciente indica presión. En la base de datos, consulte las sesiones abiertas (pg_stat_activity en PostgreSQL) para detectar conexiones "idle in transaction" o consultas largas que retienen conexiones.',
                    'Causas probables: conexiones no liberadas por el código (falta de cierre en bloques finally o using), transacciones abiertas durante llamadas externas, consultas SQL lentas que mantienen la conexión ocupada, picos de tráfico, o un tamaño de pool mal dimensionado respecto al límite max_connections del servidor.',
                    'Solución recomendada: 1) Corregir fugas de conexiones y transacciones largas. 2) Optimizar las consultas lentas (ver KB-001). 3) Configurar timeouts razonables de adquisición y de consulta. 4) Reintentar solo operaciones idempotentes, con backoff exponencial. 5) Ajustar el tamaño del pool únicamente después de validar la capacidad de la base de datos. 6) Crear una alerta preventiva al superar el 80 % de uso.',
                ],
            ],
            [
                'codigo' => 'KB-003', 'categoria' => 'Cloud', 'vistas' => 531, 'actualizado_hace_min' => 3 * 1440,
                'titulo' => 'Diagnóstico de latencia cloud',
                'resumen' => 'Guía para aislar latencia entre aplicación, red, API externa y servicios gestionados.',
                'etiquetas' => ['cloud', 'latencia', 'red', 'rendimiento', 'apis'],
                'contenido' => [
                    'La latencia en aplicaciones desplegadas en la nube puede originarse en varias capas: el código de la aplicación, la red entre zonas o regiones, servicios gestionados (bases de datos, colas, almacenamiento) o APIs externas. El objetivo del diagnóstico es medir cada salto para no adivinar.',
                    'Mida de extremo a extremo con trazas distribuidas (OpenTelemetry, X-Ray, Application Insights, Cloud Trace) e identifique qué tramo concentra el tiempo. Compare la latencia desde la misma región y desde la ubicación de los usuarios. Verifique si la base de datos y la aplicación están en la misma zona de disponibilidad.',
                    'Revise métricas de recursos: CPU con créditos agotados en instancias burstable, memoria con swapping, IOPS limitados del disco, throttling de APIs gestionadas y límites de conexiones. Un aumento de latencia junto a CPU al 100 % suele indicar recursos insuficientes; con CPU baja, apunte a dependencias externas o bloqueos.',
                    'Acciones: colocar servicios relacionados en la misma región, habilitar caché para lecturas frecuentes, usar pools de conexión, escalar horizontalmente detrás del balanceador, aplicar timeouts y circuit breakers a dependencias externas, y definir alarmas por percentil 95 de latencia en lugar del promedio.',
                ],
            ],
            [
                'codigo' => 'KB-004', 'categoria' => 'Redes', 'vistas' => 477, 'actualizado_hace_min' => 5 * 1440,
                'titulo' => 'VPN: desconexiones y pérdida de paquetes',
                'resumen' => 'Checklist de MTU, rutas, DNS, cliente VPN y estabilidad del enlace.',
                'etiquetas' => ['vpn', 'redes', 'conectividad', 'mtu', 'trabajo remoto'],
                'contenido' => [
                    'Las desconexiones de VPN al iniciar sesión remota suelen deberse a problemas de MTU, inestabilidad del enlace del usuario, expiración de sesiones, conflictos de rutas o DNS, o versiones desactualizadas del cliente VPN.',
                    'Checklist inicial: confirmar si el problema afecta a un usuario o a varios (si son varios, revisar el concentrador VPN y el enlace principal); revisar los logs del concentrador en el horario reportado; probar con cable en lugar de Wi-Fi; verificar la versión del cliente y del sistema operativo.',
                    'MTU y fragmentación: si la conexión se establece pero se cae al transferir datos, reduzca la MTU del túnel (por ejemplo a 1400) y habilite MSS clamping. Pruebe con ping -f -l para encontrar el tamaño máximo sin fragmentación. La pérdida de paquetes en el enlace principal (medida con mtr o pathping) también provoca caídas.',
                    'Rutas y DNS: verifique que no haya solapamiento entre la red doméstica del usuario y las redes corporativas, y que el DNS interno resuelva por el túnel. Revise el tiempo de vida de la sesión y la reautenticación MFA. Si hay pérdida de paquetes en el edge router, escalar al equipo de redes y aplicar el workaround de enlace secundario.',
                ],
            ],
            [
                'codigo' => 'KB-005', 'categoria' => 'Seguridad', 'vistas' => 711, 'actualizado_hace_min' => 6 * 60,
                'titulo' => 'Respuesta inicial ante accesos anómalos',
                'resumen' => 'Procedimiento de contención, preservación de evidencias y escalamiento SOC.',
                'etiquetas' => ['seguridad', 'incidente de seguridad', 'accesos', 'soc'],
                'contenido' => [
                    'Ante un acceso anómalo (inicio de sesión desde ubicación inusual, múltiples intentos fallidos, uso de cuentas inactivas o privilegios elevados no autorizados) la prioridad es contener sin destruir evidencia.',
                    'Contención: bloquear temporalmente la cuenta afectada o forzar el cambio de contraseña y el cierre de sesiones activas, revocar tokens y claves API asociados y, si hay un equipo comprometido, aislarlo de la red sin apagarlo.',
                    'Preservación de evidencias: exportar los logs de autenticación, del firewall y del servidor afectado con su sello de tiempo; registrar quién hizo qué y cuándo en el ticket; no reinstalar ni limpiar el equipo hasta que el SOC lo autorice.',
                    'Escalamiento: clasificar el ticket como incidente de seguridad con prioridad Alta o Crítica según el alcance, notificar al SOC y al responsable de la organización, y documentar el análisis de causa raíz y las medidas preventivas (MFA, mínimo privilegio, alertas de comportamiento).',
                ],
            ],
            [
                'codigo' => 'KB-006', 'categoria' => 'Hardware', 'vistas' => 320, 'actualizado_hace_min' => 7 * 1440,
                'titulo' => 'Diagnóstico de almacenamiento y SMART',
                'resumen' => 'Interpretación de métricas de disco, IOPS, latencia y alertas de hardware.',
                'etiquetas' => ['hardware', 'almacenamiento', 'disco', 'servidores', 'monitoreo'],
                'contenido' => [
                    'Las alertas de espacio en disco o de salud de almacenamiento en servidores de base de datos deben atenderse antes de que el volumen se llene, porque un disco lleno detiene escrituras y puede corromper transacciones.',
                    'Espacio: identifique qué directorios crecen (logs de la base de datos, archivos WAL o de transacciones, respaldos locales, archivos temporales). Aplique rotación de logs, mueva respaldos a almacenamiento externo y, si el crecimiento es legítimo, amplíe el volumen de forma planificada.',
                    'Salud del disco: revise los atributos SMART (sectores reasignados, sectores pendientes, errores CRC). Valores en aumento indican un disco a punto de fallar; programe el reemplazo y verifique el estado del RAID.',
                    'Rendimiento: mida IOPS y latencia (iostat, métricas del proveedor cloud). Una latencia de disco sostenida por encima de 20 ms en bases de datos provoca consultas lentas; considere discos más rápidos o reducir la carga de lectura con índices y caché.',
                ],
            ],
            [
                'codigo' => 'KB-007', 'categoria' => 'Aplicaciones', 'vistas' => 96, 'actualizado_hace_min' => 4 * 1440,
                'org' => 'andes', 'visibilidad' => 'organizacion', 'autor' => 'maria.pena@andesmarket.pe',
                'titulo' => 'ERP Corporativo: reinicio controlado del pool de conexiones',
                'resumen' => 'Procedimiento interno de AndesMarket para liberar conexiones del ERP sin afectar transacciones en curso.',
                'etiquetas' => ['erp', 'pool de conexiones', 'procedimiento'],
                'contenido' => [
                    'Este procedimiento aplica al ERP Corporativo de AndesMarket cuando el monitoreo muestra el pool de conexiones por encima del 90 % y los usuarios reportan lentitud al consultar clientes.',
                    '1) Confirmar en el panel del servidor de aplicaciones APP-ERP-01 el uso del pool y la cola de espera. 2) Identificar en DB-PROD-01 las sesiones "idle in transaction" con más de 5 minutos. 3) Coordinar con el supervisor de turno una ventana de 5 minutos.',
                    '4) Terminar solo las sesiones ociosas identificadas. 5) Reciclar el pool desde la consola de administración del ERP (no reiniciar el servidor completo). 6) Verificar que el tiempo de respuesta de la consulta de clientes vuelva a estar por debajo de 2 segundos y registrar el resultado en el ticket.',
                ],
            ],
            [
                'codigo' => 'KB-008', 'categoria' => 'Aplicaciones', 'vistas' => 0, 'actualizado_hace_min' => 300,
                'org' => 'novatech', 'visibilidad' => 'interna', 'estado' => 'borrador', 'autor' => 'luis.ramirez@novatech.pe',
                'titulo' => 'CRM Cloud: lentitud durante cierre comercial',
                'resumen' => 'Borrador con hallazgos sobre la latencia del CRM en fechas de cierre.',
                'etiquetas' => ['crm', 'latencia'],
                'contenido' => [
                    'Durante los cierres comerciales el CRM Cloud recibe hasta 4 veces más consultas de reportes. La VM APP-CRM-02 llega al 95 % de CPU y la latencia supera los 3 segundos.',
                    'Hallazgos preliminares: los reportes de pipeline se ejecutan sin caché y sobre el mismo nodo transaccional. Propuesta: programar reportes pesados fuera de horario y escalar horizontalmente durante la semana de cierre.',
                ],
            ],
        ];
    }
}
