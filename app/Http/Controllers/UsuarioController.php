<?php

namespace App\Http\Controllers;

use App\Enums\EstadoUsuario;
use App\Enums\Rol;
use App\Http\Requests\GuardarUsuarioRequest;
use App\Models\Organizacion;
use App\Models\Usuario;
use App\Services\UsuarioService;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UsuarioController extends Controller
{
    public function __construct(private UsuarioService $servicio) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Usuario::class);

        return view('usuarios.index', [
            'usuarios' => $this->consulta($request)->paginate(config('servicecloud.paginacion'))->withQueryString(),
        ]);
    }

    public function exportar(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Usuario::class);
        $filas = $this->consulta($request)->get()->map(fn (Usuario $u) => [
            $u->codigoVisible(), $u->nombre, $u->email, $u->organizacion?->razon_social ?? 'ServiceCloud AI',
            $u->rolPrincipal()?->label(), $u->estado, $u->cargo, $u->ultimo_acceso,
        ]);

        return Csv::descargar('usuarios', ['ID', 'Nombre completo', 'Correo', 'Organización', 'Rol', 'Estado', 'Cargo', 'Último acceso'], $filas);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Usuario::class);

        return $this->vistaModal('usuarios.form', $this->datosFormulario($request, new Usuario(['estado' => EstadoUsuario::Activo])));
    }

    public function store(GuardarUsuarioRequest $request): JsonResponse|RedirectResponse
    {
        $usuario = $this->servicio->guardar(null, $request->datosConOrganizacion());

        return $this->guardado(route('usuarios.index'), ['Registro creado', "Usuarios: {$usuario->nombre} fue creado."]);
    }

    public function show(Usuario $usuario): View
    {
        $this->authorize('view', $usuario);
        $usuario->load('organizacion')->loadCount(['ticketsAsignados', 'ticketsSolicitados']);

        return $this->vistaModal('usuarios.show', compact('usuario'));
    }

    public function edit(Request $request, Usuario $usuario): View
    {
        $this->authorize('update', $usuario);

        return $this->vistaModal('usuarios.form', $this->datosFormulario($request, $usuario));
    }

    public function update(GuardarUsuarioRequest $request, Usuario $usuario): JsonResponse|RedirectResponse
    {
        $this->servicio->guardar($usuario, $request->datosConOrganizacion());

        return $this->guardado(route('usuarios.index'), ['Registro actualizado', 'Usuarios: cambios guardados correctamente.']);
    }

    public function destroy(Usuario $usuario): RedirectResponse
    {
        $this->authorize('delete', $usuario);

        if ($this->servicio->eliminarODesactivar($usuario)) {
            return back()->with('ok', ['Registro eliminado', "{$usuario->nombre} fue eliminado."]);
        }

        return back()->with('aviso', ['Usuario desactivado', "{$usuario->nombre} tiene historial (tickets o comentarios); se marcó como Inactivo."]);
    }

    private function consulta(Request $request): Builder
    {
        return Usuario::query()
            ->with(['organizacion', 'roles'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $texto = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('nombre', 'ilike', $texto)->orWhere('email', 'ilike', $texto)->orWhere('cargo', 'ilike', $texto));
            })
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->when($request->filled('rol'), function ($q) use ($request) {
                $rol = (string) $request->string('rol');
                $q->whereHas('roles', fn ($r) => $r->where('codigo', $rol));
            })
            ->orderByRaw('organizacion_id nulls first')->orderBy('nombre');
    }

    private function datosFormulario(Request $request, Usuario $usuario): array
    {
        return [
            'usuario' => $usuario,
            'roles' => $this->servicio->rolesAsignables($request->user(), $usuario->exists ? $usuario : null),
            'rolActual' => $usuario->exists ? $usuario->rolPrincipal()?->value : Rol::Solicitante->value,
            'organizaciones' => $request->user()->esAdminSaas() && ! $usuario->exists
                ? Organizacion::orderBy('razon_social')->pluck('razon_social', 'id')->all()
                : [],
        ];
    }
}
