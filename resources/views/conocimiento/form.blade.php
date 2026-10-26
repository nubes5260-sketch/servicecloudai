<x-modal-form
  :titulo="$articulo->exists ? 'Editar artículo · '.$articulo->codigo : 'Nuevo artículo'"
  :accion="$articulo->exists ? route('conocimiento.update', $articulo) : route('conocimiento.store')"
  :metodo="$articulo->exists ? 'PUT' : 'POST'"
  :boton="$puedePublicar ? 'Guardar' : 'Guardar borrador'">
  @if ($organizaciones)
    <x-form-field nombre="organizacion_id" etiqueta="Organización" :opciones="$organizaciones" vacio="— Pública (todas las organizaciones) —" completo />
  @endif
  <x-form-field nombre="titulo" etiqueta="Título" :valor="$articulo->titulo" requerido />
  <x-form-field nombre="categoria" etiqueta="Categoría" :valor="$articulo->categoria" :opciones="App\Models\CategoriaConocimiento::orderBy('nombre')->pluck('nombre', 'nombre')->all()" requerido />
  <x-form-field nombre="visibilidad" etiqueta="Visibilidad" :valor="$articulo->visibilidad" :opciones="App\Enums\VisibilidadArticulo::opciones()" requerido />
  @if ($puedePublicar)
    <x-form-field nombre="estado" etiqueta="Estado" :valor="$articulo->estado" :opciones="App\Enums\EstadoArticulo::opciones()" requerido ayuda="Al publicar, el artículo queda disponible según su visibilidad." />
  @else
    <x-meta-box etiqueta="Estado" valor="Borrador (lo publica un supervisor)" />
  @endif
  <x-form-field nombre="resumen" etiqueta="Resumen" tipo="textarea" :valor="$articulo->resumen" requerido completo />
  <x-form-field nombre="contenido" etiqueta="Contenido" tipo="textarea" :valor="$articulo->contenido" requerido completo rows="10" placeholder="Síntomas, diagnóstico, causas y pasos de solución." />
  <x-form-field nombre="etiquetas" etiqueta="Etiquetas (separadas por comas)" :valor="$articulo->exists ? $articulo->etiquetas->pluck('nombre')->join(', ') : ''" completo placeholder="sql, rendimiento, timeout" />
</x-modal-form>
