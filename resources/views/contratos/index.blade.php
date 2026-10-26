@extends('layouts.app')

@section('content')
  <x-page-header :titulo="config('servicecloud.menu.contratos.titulo')" :descripcion="config('servicecloud.menu.contratos.descripcion')">
    @can('create', App\Models\Contrato::class)
      <button class="btn btn-primary" type="button" data-modal-url="{{ route('contratos.create') }}" data-modal-titulo="Nuevo · Contratos"><i data-lucide="plus"></i> Nuevo</button>
    @endcan
  </x-page-header>

  <x-toolbar-filtros placeholder="Buscar en contratos..." :estados="App\Enums\EstadoContrato::opciones()" :exportar="route('contratos.exportar', request()->query())" />

  <div class="card table-card"><div class="table-scroll"><table>
    <thead><tr><th>ID</th><th>Contrato</th><th>Organización</th><th>Inicio</th><th>Fin</th><th>Valor</th><th>Estado</th><th>Acciones</th></tr></thead>
    <tbody>
      @forelse ($contratos as $contrato)
        <tr>
          <td><span class="table-link" data-modal-url="{{ route('contratos.show', $contrato) }}">{{ $contrato->codigo }}</span></td>
          <td>{{ $contrato->nombre }}</td>
          <td>{{ $contrato->organizacion?->razon_social }}</td>
          <td>{{ App\Support\Formato::fecha($contrato->fecha_inicio) }}</td>
          <td>{{ App\Support\Formato::fecha($contrato->fecha_fin) }}</td>
          <td>{{ App\Support\Formato::moneda($contrato->monto) }}</td>
          <td><x-badge :valor="$contrato->estado" /></td>
          <td>
            <x-acciones-fila
              :ver="route('contratos.show', $contrato)"
              :editar="auth()->user()->can('update', $contrato) ? route('contratos.edit', $contrato) : null"
              :eliminar="auth()->user()->can('delete', $contrato) ? route('contratos.destroy', $contrato) : null"
              :texto-eliminar="'¿Deseas eliminar '.$contrato->codigo.'? Si tiene servicios asociados se marcará como Cancelado.'" />
          </td>
        </tr>
      @empty
        <tr><td colspan="8"><x-empty-state titulo="Sin contratos" texto="No hay registros que coincidan con los filtros." /></td></tr>
      @endforelse
    </tbody>
  </table></div>
  {{ $contratos->links() }}
  </div>
@endsection
