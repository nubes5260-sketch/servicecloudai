<x-modal-form
  :titulo="($organizacion->exists ? 'Editar' : 'Nuevo').' · Organizaciones'"
  :accion="$organizacion->exists ? route('organizaciones.update', $organizacion) : route('organizaciones.store')"
  :metodo="$organizacion->exists ? 'PUT' : 'POST'">
  <x-form-field nombre="razon_social" etiqueta="Organización" :valor="$organizacion->razon_social" requerido completo />
  <x-form-field nombre="ruc" etiqueta="RUC" :valor="$organizacion->ruc" maxlength="11" inputmode="numeric" />
  <x-form-field nombre="sector" etiqueta="Sector" :valor="$organizacion->sector" />
  <x-form-field nombre="email" etiqueta="Correo de contacto" tipo="email" :valor="$organizacion->email" />
  <x-form-field nombre="telefono" etiqueta="Teléfono" :valor="$organizacion->telefono" />
  <x-form-field nombre="estado" etiqueta="Estado" :valor="$organizacion->estado" :opciones="App\Enums\EstadoOrganizacion::opciones()" requerido />
  <x-form-field nombre="plan_id" etiqueta="Plan" :valor="$organizacion->plan_id" :opciones="$planes" vacio="Selecciona un plan" requerido />
  <x-form-field nombre="direccion" etiqueta="Dirección" :valor="$organizacion->direccion" completo />
  @unless ($organizacion->exists)
    <p class="form-hint form-group full">Al crearla se generan su configuración, las políticas SLA (Gold, Silver, Bronze), las categorías de tickets y el equipo Mesa de Ayuda.</p>
  @endunless
</x-modal-form>
