@props(['icono', 'valor', 'etiqueta', 'tendencia' => '', 'clase' => '', 'iconoTendencia' => 'trending-up'])
<div class="card kpi {{ $clase }}">
  <div class="kpi-top"><div class="kpi-icon"><i data-lucide="{{ $icono }}"></i></div><div class="kpi-trend"><i data-lucide="{{ $iconoTendencia }}"></i>{{ $tendencia }}</div></div>
  <div class="kpi-value">{{ $valor }}</div>
  <div class="kpi-label">{{ $etiqueta }}</div>
</div>
