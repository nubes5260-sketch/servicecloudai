@extends('layouts.app')

@section('content')
  <x-page-header :titulo="config('servicecloud.menu.servicios.titulo')" :descripcion="config('servicecloud.menu.servicios.descripcion')">
    @can('create', App\Models\Servicio::class)
      <button class="btn btn-primary" type="button" data-modal-url="{{ route('servicios.create') }}" data-modal-titulo="Nuevo · Servicios"><i data-lucide="plus"></i> Nuevo</button>
    @endcan
  </x-page-header>

  <x-toolbar-filtros placeholder="Buscar en servicios..." :estados="App\Enums\EstadoServicio::opciones()" :exportar="route('servicios.exportar', request()->query())" />

  <div class="card table-card"><div class="table-scroll"><table>
    <thead><tr><th>ID</th><th>Servicio</th><th>Organización</th><th>Owner</th><th>Disponibilidad</th><th>Estado</th><th>SLA</th><th>Acciones</th></tr></thead>
    <tbody>
      @forelse ($servicios as $servicio)
        <tr>
          <td><span class="table-link" data-modal-url="{{ route('servicios.show', $servicio) }}">{{ $servicio->codigo }}</span></td>
          <td>{{ $servicio->nombre }}</td>
          <td>{{ $servicio->organizacion?->razon_social }}</td>
          <td>{{ $servicio->responsable?->nombre ?? '—' }}</td>
          <td>{{ $servicio->disponibilidad_objetivo === null ? '—' : rtrim(rtrim((string) $servicio->disponibilidad_objetivo, '0'), '.').'%' }}</td>
          <td><x-badge :valor="$servicio->estado" /></td>
          <td>@if ($servicio->politicaSla)<x-badge :valor="$servicio->politicaSla->nombre" />@else — @endif</td>
          <td>
            <x-acciones-fila
              :ver="route('servicios.show', $servicio)"
              :editar="auth()->user()->can('update', $servicio) ? route('servicios.edit', $servicio) : null"
              :eliminar="auth()->user()->can('delete', $servicio) ? route('servicios.destroy', $servicio) : null"
              :texto-eliminar="'¿Deseas eliminar '.$servicio->codigo.' ('.$servicio->nombre.')? Si tiene tickets o incidencias se marcará como Retirado.'" />
          </td>
        </tr>
      @empty
        <tr><td colspan="8"><x-empty-state titulo="Sin servicios" texto="No hay registros que coincidan con los filtros." /></td></tr>
      @endforelse
    </tbody>
  </table></div>
  {{ $servicios->links() }}
  </div>
@endsection
