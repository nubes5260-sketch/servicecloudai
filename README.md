# ServiceCloud AI

Plataforma SaaS multi-organización de **IT Service Management** (mesa de ayuda): tickets con SLA, asignación automática por carga, incidencias, problemas, solicitudes, activos, servicios, contratos y base de conocimiento.

> **Primer avance (semana 6).** Este repositorio contiene lo que pide la guía para la Unidad I: la aplicación web completa, su base de datos y la API REST inicial. La inteligencia artificial (clasificar tickets y sugerir soluciones desde la base de conocimiento) se integrará en la Unidad II; el esquema de la base ya incluye sus tablas, vacías por ahora.

Proyecto N°9 del curso de Nubes (UNS). Laravel 13 · PHP 8.4 · PostgreSQL 17 · Blade + JavaScript vanilla · Docker.

## Requisitos

- **Docker Desktop** (en Windows con WSL 2) y **Git**. No hace falta instalar PHP, Composer ni PostgreSQL.

## Instalación

```powershell
git clone <url-del-repositorio> servicecloudai
cd servicecloudai
copy .env.example .env
docker compose up -d --build
docker compose exec -u root app sh -c "composer install && chown -R www-data:www-data vendor && chmod -R a+rwX storage bootstrap/cache"
docker compose exec -u root app php artisan key:generate
docker compose exec app php artisan migrate:fresh --seed
```

| Servicio | URL |
|---|---|
| Aplicación | http://localhost:8000 |
| pgAdmin | http://localhost:5050 (`admin@servicecloud.ai` / `admin`) |
| PostgreSQL | `localhost:5432` (`servicecloud` / `secret`, bases `servicecloud_ai` y `servicecloud_ai_test`) |

El servicio `scheduler` ejecuta las tareas programadas (`sla:revisar` cada 5 min, estado de contratos a diario).

## Usuarios demo

Contraseña de todos: **`password`**.

| Rol | Correo | Organización |
|---|---|---|
| Administrador SaaS | `admin@servicecloud.ai` | Plataforma |
| Administrador de organización | `rosa.villanueva@andesmarket.pe` | AndesMarket S.A.C. |
| Supervisor | `ana.torres@andesmarket.pe` | AndesMarket S.A.C. |
| Técnico de soporte | `maria.pena@andesmarket.pe` | AndesMarket S.A.C. |
| Solicitante | `lucia.vega@andesmarket.pe` | AndesMarket S.A.C. |
| Técnico (otra organización) | `luis.ramirez@novatech.pe` | Grupo NovaTech |

> **Datos sintéticos.** Todos los datos que carga `migrate:fresh --seed` son ficticios: organizaciones, RUC, personas, correos y teléfonos. Son pocos a propósito: 3 organizaciones, 12 usuarios y 12 tickets escritos a mano, uno por cada situación del flujo (del 00001 al 00012). El caso del enunciado es `TKT-2026-00010`. Se crearon para demostrar y probar el sistema y son coherentes con el dominio ITSM. No corresponden a empresas ni personas reales. La contraseña `password` es solo para la demo.

## Comandos frecuentes

Todo se ejecuta dentro de los contenedores:

> `migrate:fresh --seed` elimina las tablas existentes y las crea de nuevo con datos demo. No lo ejecutes en una base con información que quieras conservar.

| Tarea | Comando |
|---|---|
| Encender / apagar | `docker compose up -d` / `docker compose stop` |
| Reconstruir la base con datos demo | `docker compose exec app php artisan migrate:fresh --seed` |
| Tests (contra `servicecloud_ai_test`) | `docker compose exec app php artisan test` |
| Formato PSR-12 | `docker compose exec -u root app ./vendor/bin/pint` |
| Revisar SLA a mano | `docker compose exec app php artisan sla:revisar` |
| Agregar un paquete | `docker compose exec -u root app sh -c "composer require vendor/paquete && chown -R www-data:www-data vendor"` |
| Logs | `docker compose logs app --tail=100` |

## Flujo central del negocio

1. El **solicitante** reporta un problema desde "Mis tickets".
2. El sistema calcula la prioridad (impacto × urgencia) y el SLA, y lo **asigna solo** al técnico con menor carga.
3. El **técnico** lo atiende desde su cola de trabajo. Puede pedir información al usuario: el SLA queda en pausa hasta que responda.
4. El técnico lo resuelve y el **solicitante confirma** la solución, o lo reabre si sigue fallando.
5. El **supervisor** ve la carga del equipo, reasigna, gestiona incidencias, problemas y SLA, y consulta dashboard y reportes.

Cada paso notifica en la campana.

## API REST

API JSON v1 en `/api/v1` con autenticación por token Bearer (Laravel Sanctum). Aplica los mismos permisos, validaciones, SLA y aislamiento por organización que la interfaz web.

| Método | Ruta | Parámetros | Respuesta |
|---|---|---|---|
| `POST` | `/api/v1/token` | `email`, `password`, `dispositivo` | `201` con el token y los datos del usuario; `422` si los datos son inválidos |
| `DELETE` | `/api/v1/token` | — | Revoca el token usado |
| `GET` | `/api/v1/yo` | — | `200` con nombre, correo, rol y organización |
| `GET` | `/api/v1/tickets` | `estado`, `prioridad`, `q`, `por_pagina` (1–100), `page` | `200` con la lista paginada según el rol |
| `GET` | `/api/v1/tickets/{codigo}` | — | `200` con el ticket; `404` si es de otra organización |
| `POST` | `/api/v1/tickets` | `titulo`, `descripcion`, `tipo`, `impacto`, `urgencia` y opcionales | `201` con el ticket, su prioridad, el SLA y el técnico asignado; `422` con errores por campo |

Errores: `401` sin token o token vencido · `403` sin permiso · `404` no existe o pertenece a otra organización · `422` validación · `429` límite de peticiones (`API_RATE_LIMIT`, 600 por minuto por defecto). Prueba rápida:

```bash
curl -X POST http://localhost:8000/api/v1/token -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"ana.torres@andesmarket.pe","password":"password","dispositivo":"curl"}'
curl http://localhost:8000/api/v1/tickets -H "Accept: application/json" -H "Authorization: Bearer <token>"
```

## Base de datos

PostgreSQL 17 con un esquema normalizado de 46 tablas, multi-tenant, con FK compuestas por organización y triggers de integridad. El script reproducible es [`database/sql/servicecloud_ai_schema.sql`](database/sql/servicecloud_ai_schema.sql): lo carga una migración y los seeders agregan los datos demo. Las tablas de inteligencia artificial del esquema quedan vacías: se usarán en la Unidad II.

## Despliegue

| Ambiente | Archivos |
|---|---|
| Local (virtualizado con Docker) | `compose.yaml` (ver Instalación) |
| Servidor con Docker (VPS) | `compose.prod.yaml` + `compose.https.yaml` (HTTPS con Caddy) y `.env` creado desde `.env.produccion.example` |
| Máquina virtual de Google Compute Engine | Los mismos archivos que el VPS |

```bash
docker build --target production -t servicecloudai:prod .
```

La imagen arranca en el puerto **8080**, ejecuta las migraciones y cachea la configuración sola. Todo se configura con variables de entorno: plantillas `.env.example` (desarrollo) y `.env.produccion.example` (servidor), sin secretos reales.

En el servidor, la primera vez:

```bash
cp .env.produccion.example .env   # completar APP_KEY, DB_PASSWORD y APP_URL (y DOMINIO para HTTPS)
docker compose -f compose.prod.yaml -f compose.https.yaml up -d --build
docker compose -f compose.prod.yaml exec app php artisan migrate:fresh --seed --force
```

## Arquitectura

- **Monolito modular:** cada módulo tiene su controlador, Form Request, Policy y servicio de dominio en `app/Services`. La lógica no vive en las vistas, así que cada servicio puede separarse después detrás de la API.
- **API REST versionada** (`/api/v1`) con token: es el contrato para futuros clientes y servicios.
- **Contenedores sin estado:** la sesión, la caché y la cola van en la base de datos; los adjuntos, en un volumen. El programador de tareas corre en su propio contenedor (`scheduler`).
- **Multi-tenant en la base:** cada registro pertenece a una organización y las FK compuestas impiden mezclar datos entre organizaciones.

## Estructura

```
app/Enums/                Estados, prioridades y tipos (etiqueta + color de badge)
app/Models/               Un modelo por tabla; Concerns/PerteneceAOrganizacion (multi-tenant)
app/Http/Controllers/     Un controlador por módulo (+ Api/ para la API JSON)
app/Http/Requests/        Validación (FormularioTenant valida relaciones de la misma organización)
app/Policies/             Permisos por rol
app/Services/             Tickets, SLA, asignaciones, notificaciones, reportes, dashboard
database/sql/             Esquema v2 (46 tablas) cargado por una migración
database/seeders/         Datos demo sintéticos
resources/views/          Vistas Blade
public/css, public/js     Estilos y JavaScript vanilla
tests/                    Pruebas automatizadas (PHPUnit)
```
