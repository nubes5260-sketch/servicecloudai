@extends('layouts.app')

@section('content')
  <x-page-header :titulo="config('servicecloud.menu.problemas.titulo')" :descripcion="config('servicecloud.menu.problemas.descripcion')">
    @can('create', App\Models\Problema::class)
      <button class="btn btn-primary" type="button" data-modal-url="{{ route('problemas.create') }}" data-modal-titulo="Nuevo · Problemas"><i data-lucide="plus"></i> Nuevo</button>
    @endcan
  </x-page-header>

  <x-toolbar-filtros placeholder="Buscar en problemas..." :estados="App\Enums\EstadoProblema::opciones()" :exportar="route('problemas.exportar', request()->query())" />

  <div class="card table-card"><div class="table-scroll"><table>
    <thead><tr><th>ID</th><th>Problema</th><th>Servicio</th><th>Causa raíz</th><th>Responsable</th><th>Estado</th><th>Acciones</th></tr></thead>
    <tbody>
      @forelse ($problemas as $problema)
        <tr>
          <td><span class="table-link" data-modal-url="{{ route('problemas.show', $problema) }}">{{ $problema->codigo }}</span></td>
          <td>{{ $problema->titulo }}</td>
          <td>{{ $problema->servicio?->nombre ?? '—' }}</td>
          <td>{{ \Illuminate\Support\Str::limit($problema->causa_raiz ?? '—', 60) }}</td>
          <td>{{ $problema->responsable?->nombre ?? '—' }}</td>
          <td><x-badge :valor="$problema->estado" /></td>
          <td>
            <x-acciones-fila
              :ver="route('problemas.show', $problema)"
              :editar="auth()->user()->can('update', $problema) ? route('problemas.edit', $problema) : null"
              :eliminar="auth()->user()->can('delete', $problema) ? route('problemas.destroy', $problema) : null"
              :nombre="$problema->codigo" />
          </td>
        </tr>
      @empty
        <tr><td colspan="7"><x-empty-state titulo="Sin problemas" texto="No hay registros que coincidan con los filtros." /></td></tr>
      @endforelse
    </tbody>
  </table></div>
  {{ $problemas->links() }}
  </div>
@endsection
