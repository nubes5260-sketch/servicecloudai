<?php

namespace App\Http\Controllers;

use App\Enums\EstadoArticulo;
use App\Enums\Rol;
use App\Enums\VisibilidadArticulo;
use App\Http\Requests\GuardarArticuloRequest;
use App\Models\ArticuloConocimiento;
use App\Models\Etiqueta;
use App\Models\Organizacion;
use App\Models\Usuario;
use App\Support\Codigos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ConocimientoController extends Controller
{
    /** Chips del mockup (renderKnowledge). */
    public const CHIPS = ['Todas', 'Redes', 'Aplicaciones', 'Bases de datos', 'Seguridad', 'Cloud', 'Hardware'];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ArticuloConocimiento::class);
        /** @var Usuario $usuario */
        $usuario = $request->user();
        $categoria = in_array($request->query('categoria'), self::CHIPS, true) ? $request->query('categoria') : 'Todas';
        $q = trim((string) $request->query('q', ''));

        $articulos = $this->consulta($usuario)
            ->when($categoria !== 'Todas', fn ($c) => $c->deCategoria($categoria))
            ->when($q !== '', fn ($c) => $this->filtroTexto($c, $q))
            ->orderByDesc('vistas')->get();

        return view('conocimiento.index', compact('articulos', 'categoria', 'q'));
    }

    public function show(Request $request, ArticuloConocimiento $articulo): View
    {
        $this->authorize('view', $articulo);
        if ($articulo->estado === EstadoArticulo::Publicado) {
            ArticuloConocimiento::withoutTimestamps(fn () => $articulo->increment('vistas'));
        }
        $articulo->load(['autor:id,nombre', 'etiquetas', 'organizacion:id,razon_social']);

        return view('conocimiento.show', ['articulo' => $articulo]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ArticuloConocimiento::class);
        $articulo = new ArticuloConocimiento([
            'categoria' => 'Aplicaciones', 'visibilidad' => VisibilidadArticulo::Organizacion,
            'estado' => $request->user()->can('publicar', ArticuloConocimiento::class) ? EstadoArticulo::Publicado : EstadoArticulo::Borrador,
        ]);

        return $this->vistaModal('conocimiento.form', $this->datosFormulario($request, $articulo));
    }

    public function store(GuardarArticuloRequest $request): JsonResponse|RedirectResponse
    {
        $datos = $request->validated();
        $usuario = $request->user();
        $articulo = DB::transaction(function () use ($datos, $usuario, $request) {
            $articulo = ArticuloConocimiento::create([
                'organizacion_id' => $usuario->esAdminSaas() ? ($datos['organizacion_id'] ?? null) : $usuario->organizacion_id,
                'codigo' => Codigos::siguiente(ArticuloConocimiento::class, 'KB', 3),
                'categoria' => $datos['categoria'], 'titulo' => $datos['titulo'], 'resumen' => $datos['resumen'], 'contenido' => $datos['contenido'],
                'autor_id' => $usuario->id, 'visibilidad' => $datos['visibilidad'],
                'estado' => $datos['estado'] ?? EstadoArticulo::Borrador->value,
            ]);
            $this->sincronizarEtiquetas($articulo, $request->etiquetas());

            return $articulo;
        });

        return $this->guardado(route('conocimiento.show', $articulo), $articulo->estado === EstadoArticulo::Publicado
            ? ['Artículo publicado', "{$articulo->codigo} ya está disponible en la base de conocimiento."]
            : ['Borrador guardado', "{$articulo->codigo} quedó como borrador para revisión."]);
    }

    public function edit(Request $request, ArticuloConocimiento $articulo): View
    {
        $this->authorize('update', $articulo);
        $articulo->load('etiquetas');

        return $this->vistaModal('conocimiento.form', $this->datosFormulario($request, $articulo));
    }

    public function update(GuardarArticuloRequest $request, ArticuloConocimiento $articulo): JsonResponse|RedirectResponse
    {
        $datos = $request->validated();
        DB::transaction(function () use ($articulo, $datos, $request) {
            $contenidoCambia = $articulo->contenido !== $datos['contenido'] || $articulo->titulo !== $datos['titulo'];
            $articulo->update([
                'categoria' => $datos['categoria'], 'titulo' => $datos['titulo'], 'resumen' => $datos['resumen'], 'contenido' => $datos['contenido'],
                'visibilidad' => $datos['visibilidad'], 'estado' => $datos['estado'] ?? $articulo->estado->value,
                'version' => $contenidoCambia ? $articulo->version + 1 : $articulo->version,
            ]);
            $this->sincronizarEtiquetas($articulo, $request->etiquetas());
        });

        return $this->guardado(route('conocimiento.show', $articulo), ['Artículo actualizado', "{$articulo->codigo}: cambios guardados."]);
    }

    public function destroy(ArticuloConocimiento $articulo): RedirectResponse
    {
        $this->authorize('delete', $articulo);
        $articulo->delete();

        return redirect()->route('conocimiento.index')->with('ok', ['Artículo eliminado', "{$articulo->codigo} fue eliminado de la base de conocimiento."]);
    }

    /** Publicados visibles + (para el personal) borradores de su organización o propios. */
    private function consulta(Usuario $usuario): Builder
    {
        $esGestion = $usuario->esAdminSaas() || $usuario->tieneRol(Rol::AdminOrganizacion, Rol::Supervisor);
        $esPersonal = $esGestion || $usuario->tieneRol(Rol::Tecnico);

        return ArticuloConocimiento::query()
            ->where(function (Builder $q) use ($usuario, $esGestion, $esPersonal) {
                $q->where(fn ($p) => $p->where('estado', EstadoArticulo::Publicado)
                    ->when(! $esPersonal, fn ($w) => $w->where('visibilidad', '!=', VisibilidadArticulo::Interna)));
                if ($esGestion) {
                    $q->orWhere(fn ($b) => $b->where('estado', '!=', EstadoArticulo::Publicado)->whereNotNull('organizacion_id'));
                } elseif ($esPersonal) {
                    $q->orWhere('autor_id', $usuario->id);
                }
            });
    }

    /** Full-text con el índice GIN; ILIKE como respaldo para términos cortos. */
    private function filtroTexto(Builder $consulta, string $q): Builder
    {
        $like = '%'.$q.'%';

        return $consulta->where(fn ($w) => $w
            ->whereRaw("to_tsvector('spanish', coalesce(titulo,'') || ' ' || coalesce(resumen,'') || ' ' || coalesce(contenido,'')) @@ plainto_tsquery('spanish', ?)", [$q])
            ->orWhere('titulo', 'ilike', $like)->orWhere('resumen', 'ilike', $like)->orWhere('codigo', 'ilike', $like));
    }

    private function sincronizarEtiquetas(ArticuloConocimiento $articulo, array $nombres): void
    {
        $articulo->etiquetas()->sync(array_map(fn ($n) => Etiqueta::firstOrCreate(['nombre' => $n])->id, $nombres));
    }

    private function datosFormulario(Request $request, ArticuloConocimiento $articulo): array
    {
        return [
            'articulo' => $articulo,
            'puedePublicar' => $request->user()->can('publicar', ArticuloConocimiento::class),
            'organizaciones' => $request->user()->esAdminSaas() && ! $articulo->exists ? Organizacion::orderBy('razon_social')->pluck('razon_social', 'id')->all() : [],
        ];
    }
}
