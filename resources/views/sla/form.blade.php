@php
    // Tiempos sugeridos para una política nueva (equivalentes a la plantilla Gold)
    $sugeridos = ['critica' => [15, 120], 'alta' => [30, 240], 'media' => [120, 480], 'baja' => [240, 1440]];
@endphp
<x-modal-form
  :titulo="$politica->exists ? 'Editar política SLA' : 'Nueva política SLA'"
  :accion="$politica->exists ? route('sla.update', $politica) : route('sla.store')"
  :metodo="$politica->exists ? 'PUT' : 'POST'">
  @if ($organizaciones)
    <x-form-field nombre="organizacion_id" etiqueta="Organización" :opciones="$organizaciones" vacio="— Plantilla global (para nuevas organizaciones) —" completo />
  @endif
  <x-form-field nombre="nombre" etiqueta="Nombre" :valor="$politica->nombre" requerido placeholder="Gold, Silver, Bronze..." />
  <x-form-field nombre="activo" etiqueta="Estado" :valor="$politica->activo ? '1' : '0'" :opciones="['1' => 'Activa', '0' => 'Inactiva']" requerido />
  <x-form-field nombre="horario_servicio" etiqueta="Calendario de servicio" :valor="$horario" :opciones="App\Enums\HorarioServicio::opciones()" requerido completo />
  <x-form-field nombre="descripcion" etiqueta="Descripción" :valor="$politica->descripcion" completo />
  @foreach (App\Enums\Prioridad::cases() as $prioridad)
    @php($regla = $politica->reglas->first(fn ($r) => $r->prioridad === $prioridad))
    <x-form-field :nombre="'reglas['.$prioridad->value.'][respuesta]'" :etiqueta="'Respuesta · '.$prioridad->label().' (min)'" tipo="number" min="1"
      :valor="$regla?->respuesta_minutos ?? $sugeridos[$prioridad->value][0]" requerido />
    <x-form-field :nombre="'reglas['.$prioridad->value.'][resolucion]'" :etiqueta="'Resolución · '.$prioridad->label().' (min)'" tipo="number" min="1"
      :valor="$regla?->resolucion_minutos ?? $sugeridos[$prioridad->value][1]" requerido />
  @endforeach
</x-modal-form>
