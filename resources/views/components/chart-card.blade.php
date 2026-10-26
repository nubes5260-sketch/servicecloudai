{{-- chartCard() del mockup. $grafico = ['type' => ..., 'data' => ..., 'options' => ...] que dibuja scInitChart(). --}}
@props(['titulo', 'subtitulo' => '', 'id', 'grafico' => []])
<div {{ $attributes->merge(['class' => 'card chart-card']) }}>
  <div class="card-title"><div><h3>{{ $titulo }}</h3><span>{{ $subtitulo }}</span></div>@isset($accion){{ $accion }}@else<i data-lucide="more-horizontal"></i>@endisset</div>
  <div class="chart-wrap"><canvas id="{{ $id }}" data-chart='@json($grafico)'></canvas></div>
</div>
