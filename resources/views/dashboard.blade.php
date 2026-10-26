@extends('layouts.app')

@section('content')
  <x-page-header :titulo="config('servicecloud.menu.dashboard.titulo')" :descripcion="config('servicecloud.menu.dashboard.descripcion')">
    @can('modulo', 'reportes')
      <a class="btn btn-secondary" href="{{ route('reportes.index') }}"><i data-lucide="bar-chart-3"></i> Ver reportes</a>
    @endcan
    <button class="btn btn-primary" type="button" data-modal-url="{{ route('tickets.create') }}" data-modal-titulo="Nuevo ticket"><i data-lucide="plus"></i> Nuevo ticket</button>
  </x-page-header>

  @if ($cola !== null)
    {{-- Técnico: qué atender primero (por estado y vencimiento del SLA) --}}
    <div class="card card-pad cola-trabajo" id="colaTrabajo">
      <div class="card-title">
        <div><h3>Mi cola de trabajo</h3><span>Ordenada por lo que requiere acción y el vencimiento del SLA</span></div>
        <a class="table-link" href="{{ route('asignaciones.index') }}">Abrir kanban</a>
      </div>
      @forelse ($cola as ['ticket' => $ticket, 'sla' => $estadoSla, 'paso' => $paso])
        <a class="cola-item" href="{{ route('tickets.show', $ticket) }}">
          <div class="cola-prioridad prioridad-borde-{{ $ticket->prioridad->value }}"></div>
          <div class="cola-info">
            <span>{{ $ticket->codigo }} · {{ $ticket->solicitante?->nombre }}</span>
            <b>{{ $ticket->titulo }}</b>
          </div>
          <x-badge :valor="$ticket->estado" />
          <span class="cola-sla {{ in_array($estadoSla['tipo'], ['vencido', 'riesgo'], true) ? 'texto-riesgo' : '' }}">
            <i data-lucide="{{ $estadoSla['tipo'] === 'pausa' ? 'pause' : 'timer' }}"></i> {{ $estadoSla['tipo'] === 'pausa' ? 'En pausa' : $estadoSla['texto'] }}
          </span>
          <span class="cola-accion">{{ $paso['titulo'] }} <i data-lucide="arrow-right"></i></span>
        </a>
      @empty
        <x-empty-state icono="coffee" titulo="No tienes tickets pendientes" texto="Cuando te asignen uno, aparecerá aquí con su siguiente paso." />
      @endforelse
    </div>
  @endif

  <div class="grid kpi-grid">
    @foreach ($kpis as [$icono, $valor, $etiqueta, $tendencia, $clase])
      <x-kpi :icono="$icono" :valor="$valor" :etiqueta="$etiqueta" :tendencia="$tendencia" :clase="$clase" />
    @endforeach
  </div>

  <div class="grid chart-grid">
    <x-chart-card titulo="Tickets por estado" subtitulo="Distribución actual" id="chartStatus" :grafico="$graficos['estado']" />
    <x-chart-card titulo="Tickets por prioridad" subtitulo="Volumen por criticidad" id="chartPriority" :grafico="$graficos['prioridad']" />
    <x-chart-card titulo="Evolución últimos 7 días" subtitulo="Tickets creados vs resueltos" id="chartTrend" :grafico="$graficos['tendencia']" />
    <x-chart-card titulo="Cumplimiento SLA" subtitulo="Objetivo mensual 95%" id="chartSLA" :grafico="$graficos['sla']" />
  </div>

  <div class="grid dashboard-bottom">
    <div class="card insights">
      <div class="insights-head"><div class="spark"><i data-lucide="radar"></i></div><div><h3>Señales de la operación</h3><p>Puntos que requieren atención, calculados desde la base de datos</p></div></div>
      <div class="insight-list">
        @foreach ($senales as [$valor, $texto])
          <div class="insight-item"><b>{{ $valor }}</b><span>{{ $texto }}</span></div>
        @endforeach
      </div>
    </div>
    <div class="card activity">
      <div class="card-title"><h3>Actividad reciente</h3><span>Tiempo real</span></div>
      @forelse ($actividad as [$titulo, $subtitulo])
        <x-activity :titulo="$titulo" :subtitulo="$subtitulo" />
      @empty
        <x-empty-state titulo="Sin actividad" texto="Aún no hay movimientos registrados." icono="activity" />
      @endforelse
    </div>
  </div>
@endsection
