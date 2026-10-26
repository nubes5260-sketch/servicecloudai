@extends('layouts.app')

@section('content')
  <x-page-header :titulo="config('servicecloud.menu.organizaciones.titulo')" :descripcion="config('servicecloud.menu.organizaciones.descripcion')">
    @can('create', App\Models\Organizacion::class)
      <button class="btn btn-primary" type="button" data-modal-url="{{ route('organizaciones.create') }}" data-modal-titulo="Nuevo · Organizaciones"><i data-lucide="plus"></i> Nuevo</button>
    @endcan
  </x-page-header>

  <x-toolbar-filtros placeholder="Buscar en organizaciones..." :estados="App\Enums\EstadoOrganizacion::opciones()" :exportar="route('organizaciones.exportar', request()->query())" />

  <div class="card table-card"><div class="table-scroll"><table>
    <thead><tr><th>ID</th><th>Organización</th><th>Sector</th><th>Usuarios</th><th>Servicios</th><th>Estado</th><th>Plan</th><th>Acciones</th></tr></thead>
    <tbody>
      @forelse ($organizaciones as $organizacion)
        <tr>
          <td><span class="table-link" data-modal-url="{{ route('organizaciones.show', $organizacion) }}">{{ $organizacion->codigo }}</span></td>
          <td>{{ $organizacion->razon_social }}</td>
          <td>{{ $organizacion->sector ?? '—' }}</td>
          <td>{{ $organizacion->usuarios_count }}</td>
          <td>{{ $organizacion->servicios_count }}</td>
          <td><x-badge :valor="$organizacion->estado" /></td>
          <td><x-badge :valor="$organizacion->plan?->nombre" /></td>
          <td>
            <x-acciones-fila
              :ver="route('organizaciones.show', $organizacion)"
              :editar="auth()->user()->can('update', $organizacion) ? route('organizaciones.edit', $organizacion) : null"
              :eliminar="auth()->user()->can('delete', $organizacion) ? route('organizaciones.destroy', $organizacion) : null"
              :nombre="$organizacion->codigo"
              :texto-eliminar="'¿Deseas eliminar '.$organizacion->codigo.' ('.$organizacion->razon_social.')? Si tiene usuarios o tickets se marcará como Inactiva.'" />
          </td>
        </tr>
      @empty
        <tr><td colspan="8"><x-empty-state titulo="Sin organizaciones" texto="No hay registros que coincidan con los filtros." /></td></tr>
      @endforelse
    </tbody>
  </table></div>
  {{ $organizaciones->links() }}
  </div>
@endsection
