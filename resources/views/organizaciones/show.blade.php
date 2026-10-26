<x-modal-detalle
  :titulo="'Organizaciones · '.$organizacion->codigo"
  :editar="auth()->user()->can('update', $organizacion) ? route('organizaciones.edit', $organizacion) : null">
  <x-meta-box etiqueta="Organización" :valor="$organizacion->razon_social" />
  <x-meta-box etiqueta="RUC" :valor="$organizacion->ruc" />
  <x-meta-box etiqueta="Sector" :valor="$organizacion->sector" />
  <x-meta-box etiqueta="Plan"><x-badge :valor="$organizacion->plan?->nombre" /></x-meta-box>
  <x-meta-box etiqueta="Estado"><x-badge :valor="$organizacion->estado" /></x-meta-box>
  <x-meta-box etiqueta="Correo" :valor="$organizacion->email" />
  <x-meta-box etiqueta="Teléfono" :valor="$organizacion->telefono" />
  <x-meta-box etiqueta="Dirección" :valor="$organizacion->direccion" />
  <x-meta-box etiqueta="Usuarios" :valor="$organizacion->usuarios_count" />
  <x-meta-box etiqueta="Servicios" :valor="$organizacion->servicios_count" />
  <x-meta-box etiqueta="Activos" :valor="$organizacion->activos_count" />
  <x-meta-box etiqueta="Tickets" :valor="$organizacion->tickets_count" />
  <x-meta-box etiqueta="Cliente desde" :valor="App\Support\Formato::fecha($organizacion->creado_en)" />
</x-modal-detalle>
