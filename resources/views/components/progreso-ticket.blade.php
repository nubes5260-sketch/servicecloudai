{{-- Progreso del ticket: Registrado → Asignado → En atención → Resuelto → Cerrado. --}}
@props(['pasos', 'compacto' => false])
@php
    $icono = ['hecho' => 'check', 'actual' => 'circle-dot', 'pausa' => 'pause', 'pendiente' => 'circle', 'cancelado' => 'x'];
@endphp
<ol {{ $attributes->merge(['class' => 'progreso'.($compacto ? ' progreso-compacto' : '')]) }} aria-label="Progreso del ticket">
  @foreach ($pasos as $paso)
    <li class="progreso-paso is-{{ $paso['estado'] }}" @if (in_array($paso['estado'], ['actual', 'pausa'], true)) aria-current="step" @endif>
      <span class="progreso-punto"><i data-lucide="{{ $icono[$paso['estado']] }}"></i></span>
      <span class="progreso-texto">
        <b>{{ $paso['titulo'] }}</b>
        @unless ($compacto)<small>{{ $paso['detalle'] }}</small>@endunless
      </span>
    </li>
  @endforeach
</ol>
