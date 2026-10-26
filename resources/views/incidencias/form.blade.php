<x-modal-form
  :titulo="$incidencia->exists ? 'Editar incidencia · '.$incidencia->codigo : 'Nueva incidencia'"
  :accion="$incidencia->exists ? route('incidencias.update', $incidencia) : route('incidencias.store')"
  :metodo="$incidencia->exists ? 'PUT' : 'POST'">
  @if ($organizaciones)
    <x-form-field nombre="organizacion_id" etiqueta="Organización" :opciones="$organizaciones" vacio="Selecciona una organización" requerido completo />
  @endif
  <x-form-field nombre="descripcion" etiqueta="Descripción" :valor="$incidencia->descripcion" requerido completo />
  <x-form-relacion nombre="servicio_id" etiqueta="Servicio" :registros="$servicios" :valor="$incidencia->servicio_id" vacio="Sin servicio" />
  <x-form-field nombre="estado" etiqueta="Estado" :valor="$incidencia->estado" :opciones="App\Enums\EstadoIncidencia::opciones()" requerido />
  <x-form-field nombre="impacto" etiqueta="Impacto" :valor="$incidencia->impacto" :opciones="App\Enums\Impacto::opciones()" requerido />
  <x-form-field nombre="urgencia" etiqueta="Urgencia" :valor="$incidencia->urgencia" :opciones="App\Enums\Urgencia::opciones()" requerido />
  <x-form-field nombre="prioridad" etiqueta="Prioridad" :valor="$incidencia->prioridad" :opciones="App\Enums\Prioridad::opciones()" vacio="Automática (impacto × urgencia)" />
  @if ($gestion)
    <x-form-relacion nombre="responsable_id" etiqueta="Responsable" :registros="$responsables" :valor="$incidencia->responsable_id" />
    <x-form-relacion nombre="ticket_id" etiqueta="Ticket relacionado" :registros="$tickets" :valor="$incidencia->ticket_id" :campo="fn ($t) => $t->codigo.' · '.\Illuminate\Support\Str::limit($t->titulo, 50)" vacio="Sin ticket" />
    <x-form-relacion nombre="problema_id" etiqueta="Problema" :registros="$problemas" :valor="$incidencia->problema_id" :campo="fn ($p) => $p->codigo.' · '.$p->titulo" vacio="Sin problema" />
  @endif
</x-modal-form>
