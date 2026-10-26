<x-modal-form
  :titulo="($activo->exists ? 'Editar' : 'Nuevo').' · Activos'"
  :accion="$activo->exists ? route('activos.update', $activo) : route('activos.store')"
  :metodo="$activo->exists ? 'PUT' : 'POST'">
  @if ($organizaciones)
    <x-form-field nombre="organizacion_id" etiqueta="Organización" :opciones="$organizaciones" vacio="Selecciona una organización" requerido completo />
  @endif
  <x-form-field nombre="nombre" etiqueta="Nombre del activo" :valor="$activo->nombre" requerido />
  <x-form-field nombre="tipo" etiqueta="Tipo" :valor="$activo->tipo" :opciones="App\Enums\TipoActivo::opciones()" requerido />
  <x-form-field nombre="ip" etiqueta="IP" :valor="$activo->ip" placeholder="10.20.4.11 o DHCP" />
  <x-form-field nombre="hostname" etiqueta="Hostname" :valor="$activo->hostname" />
  <x-form-field nombre="estado" etiqueta="Estado" :valor="$activo->estado" :opciones="App\Enums\EstadoActivo::opciones()" requerido />
  <x-form-relacion nombre="responsable_id" etiqueta="Responsable" :registros="$responsables" :valor="$activo->responsable_id" />
  <x-form-field nombre="fabricante" etiqueta="Fabricante" :valor="$activo->fabricante" />
  <x-form-field nombre="modelo" etiqueta="Modelo" :valor="$activo->modelo" />
  <x-form-field nombre="numero_serie" etiqueta="Número de serie" :valor="$activo->numero_serie" />
  <x-form-field nombre="ubicacion" etiqueta="Ubicación" :valor="$activo->ubicacion" />
  <x-form-field nombre="proveedor_cloud" etiqueta="Proveedor cloud" :valor="$activo->proveedor_cloud" placeholder="AWS, Azure, GCP..." />
  <x-form-field nombre="region_cloud" etiqueta="Región cloud" :valor="$activo->region_cloud" />
  <x-form-relacion nombre="servicios" etiqueta="Servicios que soporta" :registros="$servicios" :valor="$activo->servicios?->pluck('id')" multiple completo ayuda="Ctrl/Cmd + clic para elegir varios." />
</x-modal-form>
