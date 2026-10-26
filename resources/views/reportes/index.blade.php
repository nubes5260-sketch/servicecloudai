@extends('layouts.app')

@section('content')
  <x-page-header :titulo="config('servicecloud.menu.reportes.titulo')" :descripcion="config('servicecloud.menu.reportes.descripcion')" />

  {{-- Extensión: período de los reportes (el mockup exportaba los datos en memoria) --}}
  <form class="toolbar" method="GET" action="{{ route('reportes.index') }}" id="formPeriodo">
    <label class="muted" for="desde" style="font-size:11px">Desde</label>
    <input class="field" type="date" id="desde" name="desde" value="{{ $desde->format('Y-m-d') }}" />
    <label class="muted" for="hasta" style="font-size:11px">Hasta</label>
    <input class="field" type="date" id="hasta" name="hasta" value="{{ $hasta->format('Y-m-d') }}" />
    <button class="btn btn-secondary" type="submit"><i data-lucide="calendar-range"></i> Aplicar período</button>
  </form>

  <div class="grid report-grid">
    @foreach ($reportes as $clave => [$icono, $titulo, $texto])
      <div class="card report-card">
        <div class="report-icon"><i data-lucide="{{ $icono }}"></i></div>
        <h3>{{ $titulo }}</h3>
        <p>{{ $texto }}<br><span class="muted">{{ $resumen[$clave] }} registros en el período.</span></p>
        <a class="btn btn-secondary btn-sm" data-report="{{ $clave }}"
           href="{{ route('reportes.descargar', ['reporte' => $clave, 'desde' => $desde->format('Y-m-d'), 'hasta' => $hasta->format('Y-m-d')]) }}"><i data-lucide="download"></i> Descargar CSV</a>
      </div>
    @endforeach
  </div>
@endsection
