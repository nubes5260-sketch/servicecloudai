@extends('layouts.app')

@section('content')
  <x-page-header :titulo="config('servicecloud.menu.solicitudes.titulo')" :descripcion="config('servicecloud.menu.solicitudes.descripcion')">
    @can('create', App\Models\Solicitud::class)
      <button class="btn btn-primary" type="button" data-modal-url="{{ route('solicitudes.create') }}" data-modal-titulo="Nuevo · Solicitudes"><i data-lucide="plus"></i> Nuevo</button>
    @endcan
  </x-page-header>

  <x-toolbar-filtros placeholder="Buscar en solicitudes..." :estados="App\Enums\EstadoSolicitud::opciones()" :exportar="route('solicitudes.exportar', request()->query())" />

  @php($usuario = auth()->user())
  <div class="card table-card"><div class="table-scroll"><table>
    <thead><tr><th>ID</th><th>Solicitud</th><th>Solicitante</th><th>Organización</th><th>Prioridad</th><th>Estado</th><th>Acciones</th></tr></thead>
    <tbody>
      @forelse ($solicitudes as $solicitud)
        <tr>
          <td><span class="table-link" data-modal-url="{{ route('solicitudes.show', $solicitud) }}">{{ $solicitud->codigo }}</span></td>
          <td>{{ $solicitud->titulo }}</td>
          <td>{{ $solicitud->solicitante?->nombre }}</td>
          <td>{{ $solicitud->organizacion?->razon_social }}</td>
          <td><x-badge :valor="$solicitud->prioridad" /></td>
          <td><x-badge :valor="$solicitud->estado" /></td>
          <td>
            <x-acciones-fila
              :ver="route('solicitudes.show', $solicitud)"
              :editar="$usuario->can('update', $solicitud) ? route('solicitudes.edit', $solicitud) : null"
              :eliminar="$usuario->can('delete', $solicitud) ? route('solicitudes.destroy', $solicitud) : null"
              :nombre="$solicitud->codigo">
              @can('aprobar', $solicitud)
                <button class="icon-mini" type="button" title="Aprobar" data-confirmar="¿Aprobar {{ $solicitud->codigo }} · {{ $solicitud->titulo }}?" data-accion="{{ route('solicitudes.transicion', [$solicitud, 'aprobar']) }}" data-metodo="PATCH" data-titulo-confirmar="Aprobar solicitud" data-boton-confirmar="Aprobar" data-icono-confirmar="check" data-clase-confirmar="btn-primary"><i data-lucide="check"></i></button>
                <button class="icon-mini" type="button" title="Rechazar" data-confirmar="¿Rechazar {{ $solicitud->codigo }}?" data-accion="{{ route('solicitudes.transicion', [$solicitud, 'rechazar']) }}" data-metodo="PATCH" data-titulo-confirmar="Rechazar solicitud" data-boton-confirmar="Rechazar" data-icono-confirmar="x"><i data-lucide="x"></i></button>
              @endcan
              @can('atender', $solicitud)
                @if ($solicitud->estado === App\Enums\EstadoSolicitud::Aprobada)
                  <button class="icon-mini" type="button" title="Iniciar atención" data-confirmar="¿Iniciar la atención de {{ $solicitud->codigo }}?" data-accion="{{ route('solicitudes.transicion', [$solicitud, 'iniciar']) }}" data-metodo="PATCH" data-titulo-confirmar="Atender solicitud" data-boton-confirmar="Iniciar" data-icono-confirmar="play" data-clase-confirmar="btn-primary"><i data-lucide="play"></i></button>
                @else
                  <button class="icon-mini" type="button" title="Completar" data-confirmar="¿Marcar {{ $solicitud->codigo }} como completada?" data-accion="{{ route('solicitudes.transicion', [$solicitud, 'completar']) }}" data-metodo="PATCH" data-titulo-confirmar="Completar solicitud" data-boton-confirmar="Completar" data-icono-confirmar="check-check" data-clase-confirmar="btn-primary"><i data-lucide="check-check"></i></button>
                @endif
              @endcan
              @if ($solicitud->solicitante_id === $usuario->id && $usuario->can('cancelar', $solicitud) && ! $usuario->can('aprobar', $solicitud))
                <button class="icon-mini" type="button" title="Cancelar" data-confirmar="¿Cancelar tu solicitud {{ $solicitud->codigo }}?" data-accion="{{ route('solicitudes.transicion', [$solicitud, 'cancelar']) }}" data-metodo="PATCH" data-titulo-confirmar="Cancelar solicitud" data-boton-confirmar="Cancelar solicitud" data-icono-confirmar="ban"><i data-lucide="ban"></i></button>
              @endif
            </x-acciones-fila>
          </td>
        </tr>
      @empty
        <tr><td colspan="7"><x-empty-state titulo="Sin solicitudes" texto="No hay registros que coincidan con los filtros." /></td></tr>
      @endforelse
    </tbody>
  </table></div>
  {{ $solicitudes->links() }}
  </div>
@endsection
