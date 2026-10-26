@extends('layouts.app')

@section('content')
  <x-page-header :titulo="config('servicecloud.menu.usuarios.titulo')" :descripcion="config('servicecloud.menu.usuarios.descripcion')">
    @can('create', App\Models\Usuario::class)
      <button class="btn btn-primary" type="button" data-modal-url="{{ route('usuarios.create') }}" data-modal-titulo="Nuevo · Usuarios"><i data-lucide="plus"></i> Nuevo</button>
    @endcan
  </x-page-header>

  <x-toolbar-filtros placeholder="Buscar en usuarios..." :estados="App\Enums\EstadoUsuario::opciones()" :exportar="route('usuarios.exportar', request()->query())">
    <select class="field" name="rol">
      <option value="">Todos los roles</option>
      @foreach (App\Enums\Rol::opciones() as $valor => $etiqueta)
        @if ($valor !== 'ADMIN_SAAS' || auth()->user()->esAdminSaas())
          <option value="{{ $valor }}" @selected(request('rol') === $valor)>{{ $etiqueta }}</option>
        @endif
      @endforeach
    </select>
  </x-toolbar-filtros>

  <div class="card table-card"><div class="table-scroll"><table>
    <thead><tr><th>ID</th><th>Nombre completo</th><th>Correo</th><th>Organización</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead>
    <tbody>
      @forelse ($usuarios as $usuario)
        <tr>
          <td><span class="table-link" data-modal-url="{{ route('usuarios.show', $usuario) }}">{{ $usuario->codigoVisible() }}</span></td>
          <td>{{ $usuario->nombre }}</td>
          <td>{{ $usuario->email }}</td>
          <td>{{ $usuario->organizacion?->razon_social ?? 'ServiceCloud AI' }}</td>
          <td>{{ $usuario->rolPrincipal()?->label() ?? '—' }}</td>
          <td><x-badge :valor="$usuario->estado" /></td>
          <td>
            <x-acciones-fila
              :ver="route('usuarios.show', $usuario)"
              :editar="auth()->user()->can('update', $usuario) ? route('usuarios.edit', $usuario) : null"
              :eliminar="auth()->user()->can('delete', $usuario) ? route('usuarios.destroy', $usuario) : null"
              :texto-eliminar="'¿Deseas eliminar a '.$usuario->nombre.'? Si tiene tickets o comentarios se marcará como Inactivo.'" />
          </td>
        </tr>
      @empty
        <tr><td colspan="7"><x-empty-state titulo="Sin usuarios" texto="No hay registros que coincidan con los filtros." /></td></tr>
      @endforelse
    </tbody>
  </table></div>
  {{ $usuarios->links() }}
  </div>
@endsection
