<x-modal-detalle
  :titulo="'Servicios · '.$servicio->codigo"
  :editar="auth()->user()->can('update', $servicio) ? route('servicios.edit', $servicio) : null">
  <x-meta-box etiqueta="Servicio" :valor="$servicio->nombre" />
  <x-meta-box etiqueta="Organización" :valor="$servicio->organizacion?->razon_social" />
  <x-meta-box etiqueta="Owner" :valor="$servicio->responsable?->nombre" />
  <x-meta-box etiqueta="Disponibilidad" :valor="$disponibilidad" />
  <x-meta-box etiqueta="Estado"><x-badge :valor="$servicio->estado" /></x-meta-box>
  <x-meta-box etiqueta="SLA">@if ($servicio->politicaSla)<x-badge :valor="$servicio->politicaSla->nombre" />@else Por defecto @endif</x-meta-box>
  <x-meta-box etiqueta="Contrato" :valor="$servicio->contrato ? $servicio->contrato->codigo.' · '.$servicio->contrato->nombre : null" />
  <x-meta-box etiqueta="Categoría" :valor="$servicio->categoria" />
  <x-meta-box etiqueta="Tickets / Incidencias" :valor="$servicio->tickets_count.' / '.$servicio->incidencias_count" />
  <x-meta-box etiqueta="Activos asociados" :valor="$servicio->activos->pluck('nombre')->join(', ')" />
  @if ($servicio->descripcion)
    <div class="form-group full"><x-meta-box etiqueta="Descripción" :valor="$servicio->descripcion" /></div>
  @endif
</x-modal-detalle>
