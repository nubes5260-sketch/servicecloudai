@extends('layouts.app')

@section('titulo', 'Inicio')

@php
    use App\Enums\EstadoTicket;
    use App\Support\Formato;
    $nombre = \Illuminate\Support\Str::before(auth()->user()->nombre, ' ');
@endphp

@section('content')
  <x-page-header :titulo="'Hola, '.$nombre" descripcion="Aquí ves en qué va cada uno de tus tickets. Te avisaremos en la campana cuando haya novedades.">
    <button class="btn btn-primary" type="button" id="newTicket" data-modal-url="{{ route('tickets.create') }}" data-modal-titulo="Reportar un problema"><i data-lucide="plus"></i> Reportar un problema</button>
  </x-page-header>

  <div class="grid resumen-solicitante">
    <div class="resumen-item"><div class="resumen-icono tono-info"><i data-lucide="wrench"></i></div><div><b>{{ $conteo['curso'] }}</b><span>En curso</span></div></div>
    <div class="resumen-item {{ $conteo['espera'] ? 'is-destacado' : '' }}"><div class="resumen-icono tono-aviso"><i data-lucide="message-circle-question"></i></div><div><b>{{ $conteo['espera'] }}</b><span>Esperan tu respuesta</span></div></div>
    <div class="resumen-item {{ $conteo['confirmar'] ? 'is-destacado' : '' }}"><div class="resumen-icono tono-exito"><i data-lucide="circle-check-big"></i></div><div><b>{{ $conteo['confirmar'] }}</b><span>Por confirmar</span></div></div>
  </div>

  <div class="card card-pad">
    <div class="card-title"><h3>Mis tickets activos</h3><a class="table-link" href="{{ route('tickets.index') }}">Ver todos</a></div>
    @forelse ($activos as $ticket)
      @php
          [$situacion, $color] = $flujo->situacionCorta($ticket);
          $mensaje = $flujo->mensajeSolicitante($ticket);
      @endphp
      <a class="mi-ticket {{ in_array($ticket->estado, [EstadoTicket::EnEspera, EstadoTicket::Resuelto], true) ? 'is-accion' : '' }}" href="{{ route('tickets.show', $ticket) }}">
        <div class="mi-ticket-head">
          <div>
            <span class="mi-ticket-codigo">{{ $ticket->codigo }} · {{ Formato::relativo($ticket->creado_en) }}</span>
            <h4>{{ $ticket->titulo }}</h4>
          </div>
          <x-badge :valor="$situacion" :color="$color" />
        </div>
        <x-progreso-ticket :pasos="$flujo->pasos($ticket)" compacto />
        <p class="mi-ticket-mensaje"><i data-lucide="{{ $mensaje['icono'] }}"></i> {{ $mensaje['titulo'] }}@if ($ticket->estado === EstadoTicket::EnEspera) — <u>responder</u>@elseif ($ticket->estado === EstadoTicket::Resuelto) — <u>confirmar solución</u>@endif</p>
      </a>
    @empty
      <x-empty-state icono="party-popper" titulo="No tienes tickets activos" texto="Si algo no funciona, usa «Reportar un problema» y te ayudaremos." />
    @endforelse
  </div>

  @if ($cerrados->isNotEmpty())
    <div class="card card-pad" style="margin-top:18px">
      <div class="card-title"><h3>Resueltos recientemente</h3><span>Últimos {{ $cerrados->count() }}</span></div>
      @foreach ($cerrados as $ticket)
        <a class="activity-row" href="{{ route('tickets.show', $ticket) }}" style="display:flex;gap:10px;align-items:center">
          <i data-lucide="badge-check" style="width:16px;color:var(--success)"></i>
          <div style="flex:1"><b>{{ $ticket->titulo }}</b><span class="muted" style="display:block;font-size:11px">{{ $ticket->codigo }} · cerrado {{ Formato::relativo($ticket->cerrado_en) }}</span></div>
        </a>
      @endforeach
    </div>
  @endif
@endsection
