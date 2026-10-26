<?php

namespace App\Providers;

use App\Enums\Rol;
use App\Models\Contrato;
use App\Models\Notificacion;
use App\Models\Organizacion;
use App\Models\PoliticaSla;
use App\Models\ReglaSla;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Observers\AuditoriaObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('es');

        // Auditoría de entidades sensibles (regla 03)
        foreach ([Organizacion::class, Usuario::class, Contrato::class, PoliticaSla::class, ReglaSla::class, Ticket::class] as $modelo) {
            $modelo::observe(AuditoriaObserver::class);
        }
        Paginator::defaultView('partials.paginacion');

        // Acceso por módulo del menú: middleware can:modulo,<clave> y @can('modulo', '<clave>')
        Gate::define('modulo', fn (Usuario $usuario, string $clave) => $usuario->puedeVerModulo($clave));
        // Configuración de la organización (SLA y asignación) y auditoría (regla 03)
        Gate::define('configurar-organizacion', fn (Usuario $usuario) => $usuario->esAdminSaas() || $usuario->tieneRol(Rol::AdminOrganizacion));
        Gate::define('ver-auditoria', fn (Usuario $usuario) => $usuario->esAdminSaas() || $usuario->tieneRol(Rol::AdminOrganizacion));

        // El login limita sus intentos en LoginController (5 por minuto por correo + IP).
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(config('servicecloud.api.limite_por_minuto'))->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('api-login', fn (Request $request) => Limit::perMinute(config('servicecloud.api.limite_login_por_minuto'))->by($request->ip()));

        View::composer('layouts.app', function ($view) {
            /** @var Usuario $usuario */
            $usuario = Auth::user();
            $menu = collect(config('servicecloud.menu'))
                ->filter(fn ($item, $clave) => $usuario->puedeVerModulo($clave));

            $notificaciones = Notificacion::de($usuario)->latest('creado_en')->latest('id')->limit(6)->get();

            $view->with([
                'usuarioActual' => $usuario,
                'menu' => $menu,
                'notificacionesHeader' => $notificaciones,
                'notificacionesNoLeidas' => Notificacion::de($usuario)->noLeidas()->count(),
            ]);
        });
    }
}
