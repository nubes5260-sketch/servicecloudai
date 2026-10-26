@extends('layouts.app')

@php
    $porcentaje = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.').'%';
@endphp

@section('content')
  <x-page-header :titulo="config('servicecloud.menu.sla.titulo')" :descripcion="config('servicecloud.menu.sla.descripcion')">
    @can('create', App\Models\PoliticaSla::class)
      <button class="btn btn-primary" type="button" id="newSLA" data-modal-url="{{ route('sla.create') }}" data-modal-titulo="Nueva política SLA"><i data-lucide="plus"></i> Nueva política SLA</button>
    @endcan
  </x-page-header>

  @if ($politicas->count() > 1)
    <form class="toolbar" method="GET" data-filtros>
      <select class="field grow" name="politica" aria-label="Política SLA">
        @foreach ($politicas as $politica)
          <option value="{{ $politica->id }}" @selected($seleccionada?->id === $politica->id)>
            {{ $politica->organizacion?->razon_social ?? 'Plantilla global' }} · {{ $politica->nombre }}{{ $politica->activo ? '' : ' (inactiva)' }}
          </option>
        @endforeach
      </select>
    </form>
  @endif

  <div class="grid sla-grid">
    @foreach ($niveles as $nivel)
      <div class="card sla-card">
        <div class="priority-mark"><i data-lucide="{{ $nivel['icono'] }}"></i></div><h3>{{ $nivel['titulo'] }}</h3>
        <div class="sla-times"><div><span>Respuesta</span><b>{{ $nivel['respuesta'] }}</b></div><div><span>Resolución</span><b>{{ $nivel['resolucion'] }}</b></div></div>
        <div class="card-title"><span>Cumplimiento actual</span><b>{{ $porcentaje($nivel['cumplimiento']) }}</b></div>
        <div class="progress"><span style="width:{{ $nivel['cumplimiento'] ?? 0 }}%"></span></div>
      </div>
    @endforeach
  </div>

  <x-chart-card titulo="Rendimiento SLA por prioridad" subtitulo="Últimos 30 días" id="slaPerformance" :grafico="$grafico" style="margin-bottom:16px">
    <x-slot:accion><x-badge :valor="$porcentaje($general)" color="blue" /></x-slot:accion>
  </x-chart-card>

  {{-- Extensión: gestión de políticas (el mockup solo simulaba "Nueva política SLA") --}}
  <div class="card table-card"><div class="table-scroll"><table>
    <thead><tr><th>Política</th><th>Organización</th><th>Horario</th><th>Crítica</th><th>Alta</th><th>Media</th><th>Baja</th><th>En uso</th><th>Estado</th><th>Acciones</th></tr></thead>
    <tbody>
      @forelse ($politicas as $politica)
        <tr>
          <td><a class="table-link" href="{{ route('sla.index', ['politica' => $politica->id]) }}"><x-badge :valor="$politica->nombre" /></a></td>
          <td>{{ $politica->organizacion?->razon_social ?? 'Plantilla global' }}</td>
          <td>{{ $politica->reglas->first()?->horario_servicio->label() ?? '—' }}</td>
          @foreach (['critica', 'alta', 'media', 'baja'] as $prioridad)
            @php($regla = $politica->reglas->first(fn ($r) => $r->prioridad->value === $prioridad))
            <td>{{ $regla ? $regla->respuesta_minutos.' / '.$regla->resolucion_minutos.' min' : '—' }}</td>
          @endforeach
          <td>{{ $uso[$politica->id] ?? 0 }}</td>
          <td>@if ($politica->activo)<x-badge valor="Activo" />@else<x-badge valor="Inactivo" />@endif</td>
          <td>
            <x-acciones-fila
              :editar="auth()->user()->can('update', $politica) ? route('sla.edit', $politica) : null"
              :eliminar="auth()->user()->can('delete', $politica) ? route('sla.destroy', $politica) : null"
              :texto-eliminar="'¿Deseas eliminar la política '.$politica->nombre.'? Si está en uso se desactivará.'" />
          </td>
        </tr>
      @empty
        <tr><td colspan="10"><x-empty-state titulo="Sin políticas SLA" texto="Crea una política para calcular vencimientos." icono="timer" /></td></tr>
      @endforelse
    </tbody>
  </table></div></div>
@endsection
