<x-modal-form
  :titulo="($problema->exists ? 'Editar' : 'Nuevo').' · Problemas'"
  :accion="$problema->exists ? route('problemas.update', $problema) : route('problemas.store')"
  :metodo="$problema->exists ? 'PUT' : 'POST'">
  @if ($organizaciones)
    <x-form-field nombre="organizacion_id" etiqueta="Organización" :opciones="$organizaciones" vacio="Selecciona una organización" requerido completo />
  @endif
  <x-form-field nombre="titulo" etiqueta="Problema" :valor="$problema->titulo" requerido completo />
  <x-form-relacion nombre="servicio_id" etiqueta="Servicio" :registros="$servicios" :valor="$problema->servicio_id" vacio="Sin servicio" />
  <x-form-field nombre="estado" etiqueta="Estado" :valor="$problema->estado" :opciones="App\Enums\EstadoProblema::opciones()" requerido />
  @if ($gestion)
    <x-form-relacion nombre="responsable_id" etiqueta="Responsable" :registros="$responsables" :valor="$problema->responsable_id" completo />
  @endif
  <x-form-field nombre="descripcion" etiqueta="Descripción" tipo="textarea" :valor="$problema->descripcion" completo />
  <x-form-field nombre="causa_raiz" etiqueta="Causa raíz" tipo="textarea" :valor="$problema->causa_raiz" completo />
  <x-form-field nombre="solucion_temporal" etiqueta="Workaround" tipo="textarea" :valor="$problema->solucion_temporal" />
  <x-form-field nombre="solucion_definitiva" etiqueta="Solución definitiva" tipo="textarea" :valor="$problema->solucion_definitiva" />
  <input type="hidden" name="tickets_enviado" value="1" />
  <x-form-relacion nombre="tickets" etiqueta="Tickets relacionados" :registros="$tickets" :valor="$problema->tickets?->pluck('id')" :campo="fn ($t) => $t->codigo.' · '.\Illuminate\Support\Str::limit($t->titulo, 55)" multiple completo ayuda="Ctrl/Cmd + clic para elegir varios." />
</x-modal-form>
