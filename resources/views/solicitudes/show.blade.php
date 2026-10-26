<x-modal-detalle
  :titulo="'Solicitudes · '.$solicitud->codigo"
  :editar="auth()->user()->can('update', $solicitud) ? route('solicitudes.edit', $solicitud) : null">
  <div class="form-group full"><x-meta-box etiqueta="Solicitud" :valor="$solicitud->titulo" /></div>
  <x-meta-box etiqueta="Solicitante" :valor="$solicitud->solicitante?->nombre" />
  <x-meta-box etiqueta="Organización" :valor="$solicitud->organizacion?->razon_social" />
  <x-meta-box etiqueta="Prioridad"><x-badge :valor="$solicitud->prioridad" /></x-meta-box>
  <x-meta-box etiqueta="Estado"><x-badge :valor="$solicitud->estado" /></x-meta-box>
  <x-meta-box etiqueta="Aprobador" :valor="$solicitud->aprobador?->nombre" />
  <x-meta-box etiqueta="Registrada" :valor="App\Support\Formato::fechaHora($solicitud->creado_en)" />
  <x-meta-box etiqueta="Aprobada / rechazada" :valor="App\Support\Formato::fechaHora($solicitud->aprobada_en)" />
  <x-meta-box etiqueta="Completada" :valor="App\Support\Formato::fechaHora($solicitud->completada_en)" />
  @if ($solicitud->descripcion)
    <div class="form-group full"><x-meta-box etiqueta="Detalle" :valor="$solicitud->descripcion" /></div>
  @endif
</x-modal-detalle>
