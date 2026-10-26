<x-modal-form
  :titulo="($usuario->exists ? 'Editar' : 'Nuevo').' · Usuarios'"
  :accion="$usuario->exists ? route('usuarios.update', $usuario) : route('usuarios.store')"
  :metodo="$usuario->exists ? 'PUT' : 'POST'">
  <x-form-field nombre="nombre" etiqueta="Nombre completo" :valor="$usuario->nombre" requerido />
  <x-form-field nombre="email" etiqueta="Correo" tipo="email" :valor="$usuario->email" requerido autocomplete="off" />
  @if ($organizaciones)
    <x-form-field nombre="organizacion_id" etiqueta="Organización" :opciones="$organizaciones" vacio="— Ninguna (solo Administrador SaaS) —" ayuda="Déjala vacía únicamente para un Administrador SaaS." />
  @else
    <x-meta-box etiqueta="Organización" :valor="$usuario->organizacion?->razon_social ?? ($usuario->exists ? 'ServiceCloud AI' : auth()->user()->organizacion?->razon_social)" />
  @endif
  <x-form-field nombre="rol" etiqueta="Rol" :valor="$rolActual" :opciones="$roles" requerido />
  <x-form-field nombre="cargo" etiqueta="Cargo" :valor="$usuario->cargo" />
  <x-form-field nombre="telefono" etiqueta="Teléfono" :valor="$usuario->telefono" />
  <x-form-field nombre="estado" etiqueta="Estado" :valor="$usuario->estado" :opciones="App\Enums\EstadoUsuario::opciones()" requerido />
  <div></div>
  <x-form-field nombre="password" :etiqueta="$usuario->exists ? 'Nueva contraseña (opcional)' : 'Contraseña'" tipo="password" :requerido="! $usuario->exists" autocomplete="new-password" ayuda="Mínimo 8 caracteres." />
  <x-form-field nombre="password_confirmation" etiqueta="Confirmar contraseña" tipo="password" autocomplete="new-password" />
</x-modal-form>
