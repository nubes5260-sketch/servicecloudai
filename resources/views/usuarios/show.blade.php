<x-modal-detalle
  :titulo="'Usuarios · '.$usuario->codigoVisible()"
  :editar="auth()->user()->can('update', $usuario) ? route('usuarios.edit', $usuario) : null">
  <x-meta-box etiqueta="Nombre completo" :valor="$usuario->nombre" />
  <x-meta-box etiqueta="Correo" :valor="$usuario->email" />
  <x-meta-box etiqueta="Organización" :valor="$usuario->organizacion?->razon_social ?? 'ServiceCloud AI'" />
  <x-meta-box etiqueta="Rol" :valor="$usuario->rolPrincipal()?->label()" />
  <x-meta-box etiqueta="Estado"><x-badge :valor="$usuario->estado" /></x-meta-box>
  <x-meta-box etiqueta="Cargo" :valor="$usuario->cargo" />
  <x-meta-box etiqueta="Teléfono" :valor="$usuario->telefono" />
  <x-meta-box etiqueta="Último acceso" :valor="App\Support\Formato::fechaHora($usuario->ultimo_acceso)" />
  <x-meta-box etiqueta="Tickets asignados" :valor="$usuario->tickets_asignados_count" />
  <x-meta-box etiqueta="Tickets registrados" :valor="$usuario->tickets_solicitados_count" />
</x-modal-detalle>
