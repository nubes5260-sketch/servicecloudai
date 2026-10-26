@extends('layouts.app')

@section('content')
  <x-page-header :titulo="$esSolicitante ? 'Mis tickets' : config('servicecloud.menu.tickets.titulo')" :descripcion="$esSolicitante ? 'Todo lo que reportaste y en qué va cada caso.' : config('servicecloud.menu.tickets.descripcion')">
    @can('create', App\Models\Ticket::class)
      <button class="btn btn-primary" type="button" id="newTicket" data-modal-url="{{ route('tickets.create') }}" data-modal-titulo="{{ $esSolicitante ? 'Reportar un problema' : 'Nuevo ticket' }}"><i data-lucide="plus"></i> {{ $esSolicitante ? 'Reportar un problema' : 'Nuevo ticket' }}</button>
    @endcan
  </x-page-header>

  <x-toolbar-filtros placeholder="Buscar por código, título, organización..." :estados="App\Enums\EstadoTicket::opciones()" :exportar="route('tickets.exportar', request()->query())">
    <select class="field" name="prioridad">
      <option value="">Todas las prioridades</option>
      @foreach (array_reverse(App\Enums\Prioridad::opciones(), true) as $valor => $etiqueta)
        <option value="{{ $valor }}" @selected(request('prioridad') === $valor)>{{ $etiqueta }}</option>
      @endforeach
    </select>
  </x-toolbar-filtros>

  <div class="card table-card"><div class="table-scroll"><table id="ticketsTable">
    <thead><tr>
      <th>Código</th><th>Título</th>
      @if ($esSolicitante)
        <th>Situación</th><th>Técnico</th><th>Prioridad</th><th>Actualizado</th><th>Fecha</th>
      @else
        <th>Organización</th><th>Prioridad</th><th>Estado</th><th>Técnico</th><th>SLA</th><th>Fecha</th>
      @endif
    </tr></thead>
    <tbody>
      @forelse ($tickets as $ticket)
        <tr data-href="{{ route('tickets.show', $ticket) }}" style="cursor:pointer">
          <td><a class="table-link" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->codigo }}</a></td>
          <td style="max-width:340px">{{ $ticket->titulo }}</td>
          @if ($esSolicitante)
            @php([$situacion, $color] = $flujo->situacionCorta($ticket))
            <td><x-badge :valor="$situacion" :color="$color" /></td>
            <td>{{ $ticket->tecnico?->nombre ?? 'Por asignar' }}</td>
            <td><x-badge :valor="$ticket->prioridad" /></td>
            <td>{{ App\Support\Formato::relativo($ticket->actualizado_en ?? $ticket->creado_en) }}</td>
            <td>{{ App\Support\Formato::fechaHora($ticket->creado_en) }}</td>
          @else
            @php($estadoSla = $sla->estadoSla($ticket))
            <td>{{ $ticket->organizacion?->razon_social }}</td>
            <td><x-badge :valor="$ticket->prioridad" /></td>
            <td><x-badge :valor="$ticket->estado" /></td>
            <td>{{ $ticket->tecnico?->nombre ?? 'Sin asignar' }}</td>
            <td>
              @switch($estadoSla['tipo'])
                @case('cumplido') <x-badge valor="Cumplido" color="green" /> @break
                @case('incumplido') <x-badge valor="Incumplido" color="red" /> @break
                @case('sin') — @break
                @default
                  <span class="{{ in_array($estadoSla['tipo'], ['vencido', 'riesgo'], true) ? 'texto-riesgo' : 'texto-sla' }}" @if ($estadoSla['tipo'] === 'pausa') title="SLA en pausa (en espera)" @endif>{{ $estadoSla['texto'] }}</span>
              @endswitch
            </td>
            <td>{{ App\Support\Formato::fechaHora($ticket->creado_en) }}</td>
          @endif
        </tr>
      @empty
        <tr><td colspan="8"><x-empty-state titulo="Sin tickets" :texto="$esSolicitante ? 'Aún no reportaste ningún problema.' : 'No hay tickets que coincidan con los filtros.'" /></td></tr>
      @endforelse
    </tbody>
  </table></div>
  {{ $tickets->links() }}
  </div>
@endsection
