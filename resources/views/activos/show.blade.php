<x-modal-detalle
  :titulo="'Activos · '.$activo->codigo"
  :editar="auth()->user()->can('update', $activo) ? route('activos.edit', $activo) : null">
  <x-meta-box etiqueta="Nombre del activo" :valor="$activo->nombre" />
  <x-meta-box etiqueta="Tipo" :valor="$activo->tipo->label()" />
  <x-meta-box etiqueta="Organización" :valor="$activo->organizacion?->razon_social" />
  <x-meta-box etiqueta="Estado"><x-badge :valor="$activo->estado" /></x-meta-box>
  <x-meta-box etiqueta="IP" :valor="$activo->ip" />
  <x-meta-box etiqueta="Hostname" :valor="$activo->hostname" />
  <x-meta-box etiqueta="Responsable" :valor="$activo->responsable?->nombre" />
  <x-meta-box etiqueta="Fabricante / Modelo" :valor="trim($activo->fabricante.' '.$activo->modelo)" />
  <x-meta-box etiqueta="Número de serie" :valor="$activo->numero_serie" />
  <x-meta-box etiqueta="Ubicación" :valor="$activo->ubicacion" />
  <x-meta-box etiqueta="Cloud" :valor="trim($activo->proveedor_cloud.' '.$activo->region_cloud)" />
  <x-meta-box etiqueta="Tickets relacionados" :valor="$activo->tickets_count" />
  <div class="form-group full">
    <x-meta-box etiqueta="Servicios que soporta" :valor="$activo->servicios->pluck('nombre')->join(', ')" />
  </div>
</x-modal-detalle>
