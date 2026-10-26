@extends('layouts.app')

@section('content')
  <x-page-header :titulo="config('servicecloud.menu.incidencias.titulo')" :descripcion="config('servicecloud.menu.incidencias.descripcion')">
    @can('create', App\Models\Incidencia::class)
      <button class="btn btn-primary" type="button" id="newIncident" data-modal-url="{{ route('incidencias.create') }}" data-modal-titulo="Nueva incidencia"><i data-lucide="plus"></i> Nueva incidencia</button>
    @endcan
  </x-page-header>

  <x-toolbar-filtros placeholder="Buscar incidencias..." :estados="App\Enums\EstadoIncidencia::opciones()" :exportar="route('incidencias.exportar', request()->query())">
    <select class="field" name="prioridad">
      <option value="">Todas las prioridades</option>
      @foreach (array_reverse(App\Enums\Prioridad::opciones(), true) as $valor => $etiqueta)
        <option value="{{ $valor }}" @selected(request('prioridad') === $valor)>{{ $etiqueta }}</option>
      @endforeach
    </select>
  </x-toolbar-filtros>

  <div class="card table-card"><div class="table-scroll"><table id="incTable">
    <thead><tr><th>ID</th><th>Descripción</th><th>Servicio</th><th>Impacto</th><th>Urgencia</th><th>Prioridad</th><th>Responsable</th><th>Estado</th><th>Acciones</th></tr></thead>
    <tbody>
      @forelse ($incidencias as $incidencia)
        <tr>
          <td><span class="table-link" data-modal-url="{{ route('incidencias.show', $incidencia) }}">{{ $incidencia->codigo }}</span></td>
          <td>{{ $incidencia->descripcion }}</td>
          <td>{{ $incidencia->servicio?->nombre ?? '—' }}</td>
          <td><x-badge :valor="$incidencia->impacto" /></td>
          <td><x-badge :valor="$incidencia->urgencia" /></td>
          <td><x-badge :valor="$incidencia->prioridad" /></td>
          <td>{{ $incidencia->responsable?->nombre ?? '—' }}</td>
          <td><x-badge :valor="$incidencia->estado" /></td>
          <td>
            <x-acciones-fila
              :ver="route('incidencias.show', $incidencia)"
              :editar="auth()->user()->can('update', $incidencia) ? route('incidencias.edit', $incidencia) : null"
              :eliminar="auth()->user()->can('delete', $incidencia) ? route('incidencias.destroy', $incidencia) : null"
              :nombre="$incidencia->codigo" />
          </td>
        </tr>
      @empty
        <tr><td colspan="9"><x-empty-state titulo="Sin incidencias" texto="No hay incidencias que coincidan con los filtros." /></td></tr>
      @endforelse
    </tbody>
  </table></div>
  {{ $incidencias->links() }}
  </div>
@endsection
