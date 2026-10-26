{{-- settingRow() del mockup: interruptor que actualiza un campo oculto (0/1) del formulario. --}}
@props(['titulo', 'texto', 'nombre' => null, 'activo' => false, 'fijo' => false])
<div class="settings-row">
  <div><b>{{ $titulo }}</b><span>{{ $texto }}</span></div>
  @if ($fijo)
    <button type="button" class="switch on" aria-label="{{ $titulo }}" title="Siempre activo" disabled style="opacity:.7;cursor:not-allowed"></button>
  @else
    <input type="hidden" name="{{ $nombre }}" value="{{ $activo ? 1 : 0 }}" />
    <button type="button" class="switch {{ $activo ? 'on' : '' }}" data-setting="{{ $nombre }}" aria-label="{{ $titulo }}" aria-pressed="{{ $activo ? 'true' : 'false' }}"></button>
  @endif
</div>
