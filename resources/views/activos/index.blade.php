@extends('layouts.app')

@section('content')
  <x-page-header :titulo="config('servicecloud.menu.activos.titulo')" :descripcion="config('servicecloud.menu.activos.descripcion')">
    @can('create', App\Models\Activo::class)
      <button class="btn btn-primary" type="button" data-modal-url="{{ route('activos.create') }}" data-modal-titulo="Nuevo · Activos"><i data-lucide="plus"></i> Nuevo</button>
    @endcan
  </x-page-header>

  <x-toolbar-filtros placeholder="Buscar en activos..." :estados="App\Enums\EstadoActivo::opciones()" :exportar="route('activos.exportar', request()->query())">
    <select class="field" name="tipo">
      <option value="">Todos los tipos</option>
      @foreach (App\Enums\TipoActivo::opciones() as $valor => $etiqueta)
        <option value="{{ $valor }}" @selected(request('tipo') === $valor)>{{ $etiqueta }}</option>
      @endforeach
    </select>
  </x-toolbar-filtros>

  <div class="card table-card"><div class="table-scroll"><table>
    <thead><tr><th>ID</th><th>Nombre del activo</th><th>Tipo</th><th>Organización</th><th>IP / Host</th><th>Estado</th><th>Responsable</th><th>Acciones</th></tr></thead>
    <tbody>
      @forelse ($activos as $activo)
        <tr>
          <td><span class="table-link" data-modal-url="{{ route('activos.show', $activo) }}">{{ $activo->codigo }}</span></td>
          <td>{{ $activo->nombre }}</td>
          <td>{{ $activo->tipo->label() }}</td>
          <td>{{ $activo->organizacion?->razon_social }}</td>
          <td>{{ $activo->ip ?: ($activo->hostname ?: '—') }}</td>
          <td><x-badge :valor="$activo->estado" /></td>
          <td>{{ $activo->responsable?->nombre ?? '—' }}</td>
          <td>
            <x-acciones-fila
              :ver="route('activos.show', $activo)"
              :editar="auth()->user()->can('update', $activo) ? route('activos.edit', $activo) : null"
              :eliminar="auth()->user()->can('delete', $activo) ? route('activos.destroy', $activo) : null"
              :nombre="$activo->codigo" />
          </td>
        </tr>
      @empty
        <tr><td colspan="8"><x-empty-state titulo="Sin activos" texto="No hay registros que coincidan con los filtros." /></td></tr>
      @endforelse
    </tbody>
  </table></div>
  {{ $activos->links() }}
  </div>
@endsection
