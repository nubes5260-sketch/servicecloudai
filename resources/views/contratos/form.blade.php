<x-modal-form
  :titulo="($contrato->exists ? 'Editar' : 'Nuevo').' · Contratos'"
  :accion="$contrato->exists ? route('contratos.update', $contrato) : route('contratos.store')"
  :metodo="$contrato->exists ? 'PUT' : 'POST'">
  @if ($organizaciones)
    <x-form-field nombre="organizacion_id" etiqueta="Organización" :opciones="$organizaciones" vacio="Selecciona una organización" requerido completo />
  @endif
  <x-form-field nombre="nombre" etiqueta="Contrato" :valor="$contrato->nombre" requerido completo />
  <x-form-field nombre="fecha_inicio" etiqueta="Inicio" tipo="date" :valor="$contrato->fecha_inicio?->format('Y-m-d')" requerido />
  <x-form-field nombre="fecha_fin" etiqueta="Fin" tipo="date" :valor="$contrato->fecha_fin?->format('Y-m-d')" requerido />
  <x-form-field nombre="monto" etiqueta="Valor (S/)" tipo="number" :valor="$contrato->monto" step="0.01" min="0" requerido />
  <x-form-field nombre="estado" etiqueta="Estado" :valor="$contrato->estado" :opciones="App\Enums\EstadoContrato::opciones()" requerido ayuda="Vigente, Por vencer y Vencido se ajustan solos a la fecha de fin." />
  <x-form-relacion nombre="politica_sla_id" etiqueta="Política SLA" :registros="$politicas" :valor="$contrato->politica_sla_id" vacio="Sin política" completo />
  <x-form-field nombre="descripcion" etiqueta="Descripción / cobertura" tipo="textarea" :valor="$contrato->descripcion" completo />
</x-modal-form>
