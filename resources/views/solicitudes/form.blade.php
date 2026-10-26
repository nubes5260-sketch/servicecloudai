<x-modal-form
  :titulo="($solicitud->exists ? 'Editar' : 'Nuevo').' · Solicitudes'"
  :accion="$solicitud->exists ? route('solicitudes.update', $solicitud) : route('solicitudes.store')"
  :metodo="$solicitud->exists ? 'PUT' : 'POST'">
  @if ($organizaciones)
    <x-form-field nombre="organizacion_id" etiqueta="Organización" :opciones="$organizaciones" vacio="Selecciona una organización" requerido completo />
  @endif
  <x-form-field nombre="titulo" etiqueta="Solicitud" :valor="$solicitud->titulo" requerido completo placeholder="Ej.: Nuevo acceso a Power BI" />
  <x-form-field nombre="prioridad" etiqueta="Prioridad" :valor="$solicitud->prioridad" :opciones="App\Enums\Prioridad::opciones()" requerido />
  @if ($solicitantes->isNotEmpty())
    <x-form-relacion nombre="solicitante_id" etiqueta="Solicitante" :registros="$solicitantes" vacio="Yo mismo" />
  @else
    <x-meta-box etiqueta="Solicitante" :valor="$solicitud->solicitante?->nombre ?? auth()->user()->nombre" />
  @endif
  <x-form-field nombre="descripcion" etiqueta="Detalle" tipo="textarea" :valor="$solicitud->descripcion" completo placeholder="Qué necesitas y para qué." />
</x-modal-form>
