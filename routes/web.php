<?php

use App\Http\Controllers\ActivoController;
use App\Http\Controllers\AsignacionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BusquedaController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\ConocimientoController;
use App\Http\Controllers\ContratoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IncidenciaController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\OrganizacionController;
use App\Http\Controllers\ProblemaController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\SlaController;
use App\Http\Controllers\SolicitudController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

// --- Autenticación ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// --- Aplicación ---
// Cada módulo exige el permiso de su entrada de menú (config/servicecloud.php: can:modulo,'<clave>');
// las acciones concretas las autorizan las Policies.
Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/buscar', BusquedaController::class)->name('buscar');

    // CRUD con exportación CSV: clave => [controlador, parámetro de ruta]
    $crud = [
        'organizaciones' => [OrganizacionController::class, 'organizacion'],
        'usuarios' => [UsuarioController::class, 'usuario'],
        'activos' => [ActivoController::class, 'activo'],
        'servicios' => [ServicioController::class, 'servicio'],
        'contratos' => [ContratoController::class, 'contrato'],
        'incidencias' => [IncidenciaController::class, 'incidencia'],
        'problemas' => [ProblemaController::class, 'problema'],
        'solicitudes' => [SolicitudController::class, 'solicitud'],
    ];
    foreach ($crud as $clave => [$controlador, $parametro]) {
        Route::middleware("can:modulo,'$clave'")->group(function () use ($clave, $controlador, $parametro) {
            Route::get("/$clave/exportar", [$controlador, 'exportar'])->name("$clave.exportar");
            Route::resource($clave, $controlador)->parameters([$clave => $parametro]);
        });
    }

    Route::patch('/solicitudes/{solicitud}/{accion}', [SolicitudController::class, 'transicion'])
        ->middleware("can:modulo,'solicitudes'")
        ->whereIn('accion', ['aprobar', 'rechazar', 'iniciar', 'completar', 'cancelar'])
        ->name('solicitudes.transicion');

    // Tickets
    Route::middleware("can:modulo,'tickets'")->controller(TicketController::class)->group(function () {
        Route::get('/tickets/exportar', 'exportar')->name('tickets.exportar');
        Route::resource('tickets', TicketController::class)->except('destroy');
        Route::patch('/tickets/{ticket}/estado', 'cambiarEstado')->name('tickets.estado');
        Route::patch('/tickets/{ticket}/confirmacion', 'responderSolucion')->name('tickets.confirmacion');
        Route::get('/tickets/{ticket}/reasignar', 'formularioReasignar')->name('tickets.reasignar.form');
        Route::patch('/tickets/{ticket}/reasignar', 'reasignar')->name('tickets.reasignar');
        Route::post('/tickets/{ticket}/solicitar-reasignacion', 'solicitarReasignacion')->name('tickets.solicitar-reasignacion');
        Route::post('/tickets/{ticket}/comentarios', 'comentar')->name('tickets.comentarios');
        Route::get('/tickets/{ticket}/adjuntos/{adjunto}', 'descargarAdjunto')->name('tickets.adjuntos.descargar');
    });

    // Asignaciones (kanban)
    Route::middleware("can:modulo,'asignaciones'")->controller(AsignacionController::class)->group(function () {
        Route::get('/asignaciones', 'index')->name('asignaciones.index');
        Route::post('/asignaciones/autoasignar', 'autoasignar')->name('asignaciones.autoasignar');
        Route::patch('/asignaciones/{ticket}', 'mover')->name('asignaciones.mover');
    });

    // SLA (políticas y reglas)
    Route::middleware("can:modulo,'sla'")->group(function () {
        Route::resource('sla', SlaController::class)->parameters(['sla' => 'politica'])->except('show');
    });

    // Base de conocimiento
    Route::middleware("can:modulo,'conocimiento'")->group(function () {
        Route::resource('conocimiento', ConocimientoController::class)->parameters(['conocimiento' => 'articulo']);
    });

    // Notificaciones (cada usuario las suyas)
    Route::middleware("can:modulo,'notificaciones'")->controller(NotificacionController::class)->group(function () {
        Route::get('/notificaciones', 'index')->name('notificaciones.index');
        Route::get('/notificaciones/resumen', 'resumen')->name('notificaciones.resumen');
        Route::get('/notificaciones/{notificacion}/abrir', 'abrir')->name('notificaciones.abrir');
        Route::post('/notificaciones/marcar-todas', 'marcarTodas')->name('notificaciones.marcar-todas');
    });

    // Reportes CSV
    Route::middleware("can:modulo,'reportes'")->controller(ReporteController::class)->group(function () {
        Route::get('/reportes', 'index')->name('reportes.index');
        Route::get('/reportes/descargar', 'descargar')->name('reportes.descargar');
    });

    // Configuración y auditoría
    Route::middleware("can:modulo,'configuracion'")->controller(ConfiguracionController::class)->group(function () {
        Route::get('/configuracion', 'index')->name('configuracion.index');
        Route::post('/configuracion', 'guardar')->name('configuracion.guardar');
        Route::get('/configuracion/auditoria', 'auditoria')->name('configuracion.auditoria');
    });
});
