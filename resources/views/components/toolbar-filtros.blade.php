{{-- Toolbar del mockup (búsqueda, filtro de estado, Exportar) con filtros en el servidor. --}}
@props(['placeholder' => 'Buscar...', 'estados' => [], 'textoEstados' => 'Todos los estados', 'exportar' => null])
<form class="toolbar" method="GET" data-filtros>
  <input class="field grow" type="search" name="q" value="{{ request('q') }}" placeholder="{{ $placeholder }}" data-buscar />
  @if ($estados)
    <select class="field" name="estado">
      <option value="">{{ $textoEstados }}</option>
      @foreach ($estados as $valor => $etiqueta)
        <option value="{{ $valor }}" @selected(request('estado') === (string) $valor)>{{ $etiqueta }}</option>
      @endforeach
    </select>
  @endif
  {{ $slot }}
  @if ($exportar)
    <a class="btn btn-secondary" href="{{ $exportar }}"><i data-lucide="download"></i> Exportar</a>
  @endif
</form>
