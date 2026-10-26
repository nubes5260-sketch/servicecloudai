<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\ConfiguracionOrganizacion;
use App\Models\Organizacion;
use App\Models\PlanSaas;
use App\Models\PoliticaSla;
use App\Models\PreferenciaNotificacion;
use App\Models\Usuario;
use App\Services\CargaTecnicos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Configuración (renderSettings): atención y asignación de la organización (configuracion_organizacion),
 * preferencias personales (preferencias_notificaciones), planes SaaS y auditoría.
 */
class ConfiguracionController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();
        $organizacion = $this->organizacionConfigurable($request);

        return view('configuracion.index', [
            'organizacion' => $organizacion,
            'organizaciones' => $usuario->esAdminSaas() ? Organizacion::orderBy('razon_social')->pluck('razon_social', 'id')->all() : [],
            'config' => $organizacion ? ConfiguracionOrganizacion::firstOrCreate(['organizacion_id' => $organizacion->id]) : null,
            'politicas' => $organizacion ? PoliticaSla::where('organizacion_id', $organizacion->id)->where('activo', true)->orderBy('nombre')->pluck('nombre', 'id')->all() : [],
            'preferencias' => PreferenciaNotificacion::firstOrCreate(['usuario_id' => $usuario->id]),
            'planes' => $usuario->esAdminSaas() ? PlanSaas::withCount('organizaciones')->orderBy('id')->get() : collect(),
            'auditoria' => Gate::allows('ver-auditoria') ? Auditoria::with('usuario:id,nombre')->latest('fecha')->latest('id')->limit(8)->get() : collect(),
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $usuario = $request->user();
        $organizacion = $this->organizacionConfigurable($request);
        $booleano = ['required', 'boolean'];

        $datos = $request->validate([
            'email_activo' => $booleano, 'push_activo' => $booleano, 'in_app_activo' => $booleano,
            'asignacion_automatica' => $organizacion ? ['sometimes', 'boolean'] : ['prohibited'],
            'carga_maxima' => $organizacion ? ['sometimes', 'integer', 'min:1', 'max:50'] : ['prohibited'],
            'politica_sla_defecto_id' => $organizacion
                ? ['nullable', 'integer', Rule::exists('politicas_sla', 'id')->where('organizacion_id', $organizacion->id)]
                : ['prohibited'],
        ]);

        PreferenciaNotificacion::updateOrCreate(['usuario_id' => $usuario->id], [
            'email_activo' => $datos['email_activo'], 'push_activo' => $datos['push_activo'], 'in_app_activo' => $datos['in_app_activo'],
        ]);

        if ($organizacion) {
            $config = ConfiguracionOrganizacion::firstOrCreate(['organizacion_id' => $organizacion->id]);
            $config->update([
                'configuracion' => array_merge($config->configuracion ?? [], [
                    'politica_sla_defecto_id' => $datos['politica_sla_defecto_id'] ?? null,
                    'asignacion_automatica' => (bool) ($datos['asignacion_automatica'] ?? $config->configuracion['asignacion_automatica'] ?? true),
                    'carga_maxima' => (int) ($datos['carga_maxima'] ?? $config->configuracion['carga_maxima'] ?? CargaTecnicos::MAXIMO_POR_DEFECTO),
                ]),
            ]);
        }

        return redirect()->route('configuracion.index', array_filter(['organizacion' => $usuario->esAdminSaas() ? $organizacion?->id : null]))
            ->with('ok', ['Configuración guardada', 'Las preferencias fueron actualizadas.']);
    }

    public function auditoria(Request $request): View
    {
        Gate::authorize('ver-auditoria');
        $registros = Auditoria::with(['usuario:id,nombre', 'organizacion:id,razon_social'])
            ->when($request->filled('entidad'), fn ($q) => $q->where('entidad', $request->string('entidad')))
            ->when($request->filled('accion'), fn ($q) => $q->where('accion', $request->string('accion')))
            ->latest('fecha')->latest('id')
            ->paginate(25)->withQueryString();

        return view('configuracion.auditoria', [
            'registros' => $registros,
            'entidades' => Auditoria::query()->distinct()->orderBy('entidad')->pluck('entidad'),
        ]);
    }

    /** Organización que se configura: la propia (ADMIN_ORGANIZACION) o la elegida (ADMIN_SAAS). */
    private function organizacionConfigurable(Request $request): ?Organizacion
    {
        $usuario = $request->user();
        if (! Gate::allows('configurar-organizacion')) {
            return null;
        }
        if ($usuario->esAdminSaas()) {
            $id = $request->integer('organizacion') ?: Organizacion::orderBy('codigo')->value('id');

            return Organizacion::find($id);
        }

        return $usuario->organizacion;
    }
}
