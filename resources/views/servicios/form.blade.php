<x-modal-form
  :titulo="($servicio->exists ? 'Editar' : 'Nuevo').' · Servicios'"
  :accion="$servicio->exists ? route('servicios.update', $servicio) : route('servicios.store')"
  :metodo="$servicio->exists ? 'PUT' : 'POST'">
  @if ($organizaciones)
    <x-form-field nombre="organizacion_id" etiqueta="Organización" :opciones="$organizaciones" vacio="Selecciona una organización" requerido completo />
  @endif
  <x-form-field nombre="nombre" etiqueta="Servicio" :valor="$servicio->nombre" requerido />
  <x-form-field nombre="categoria" etiqueta="Categoría" :valor="$servicio->categoria" placeholder="Aplicaciones, Redes, Cloud..." list="categoriasServicio" autocomplete="off" ayuda="Elige una existente o escribe una nueva: se agrega al catálogo." />
  <datalist id="categoriasServicio">@foreach ($categorias as $nombreCategoria)<option value="{{ $nombreCategoria }}"></option>@endforeach</datalist>
  <x-form-relacion nombre="responsable_id" etiqueta="Owner" :registros="$responsables" :valor="$servicio->responsable_id" />
  <x-form-field nombre="disponibilidad_objetivo" etiqueta="Disponibilidad objetivo (%)" tipo="number" :valor="$servicio->disponibilidad_objetivo" step="0.01" min="0" max="100" />
  <x-form-field nombre="estado" etiqueta="Estado" :valor="$servicio->estado" :opciones="App\Enums\EstadoServicio::opciones()" requerido />
  <x-form-relacion nombre="politica_sla_id" etiqueta="SLA" :registros="$politicas" :valor="$servicio->politica_sla_id" vacio="Política por defecto de la organización" />
  <x-form-relacion nombre="contrato_id" etiqueta="Contrato" :registros="$contratos" :valor="$servicio->contrato_id" :campo="fn ($c) => $c->codigo.' · '.$c->nombre" vacio="Sin contrato" completo />
  <x-form-field nombre="descripcion" etiqueta="Descripción" tipo="textarea" :valor="$servicio->descripcion" completo />
  <x-form-relacion nombre="activos" etiqueta="Activos asociados" :registros="$activos" :valor="$servicio->activos?->pluck('id')" :campo="fn ($a) => $a->codigo.' · '.$a->nombre" multiple completo ayuda="Ctrl/Cmd + clic para elegir varios." />
</x-modal-form>
