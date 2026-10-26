{{-- formField() del mockup con error de validación debajo del campo. --}}
@props(['nombre', 'etiqueta', 'tipo' => 'text', 'valor' => null, 'opciones' => null, 'requerido' => false, 'completo' => false, 'vacio' => null, 'ayuda' => null])
@php
    $actual = old($nombre, $valor instanceof \BackedEnum ? $valor->value : $valor);
    $id = 'f_'.str_replace(['[', ']'], '_', $nombre);
@endphp
<div class="form-group {{ $completo ? 'full' : '' }}" data-campo="{{ $nombre }}">
  <label for="{{ $id }}">{{ $etiqueta }}</label>
  @if ($opciones !== null)
    <select class="form-control @error($nombre) is-invalid @enderror" id="{{ $id }}" name="{{ $nombre }}" @required($requerido) {{ $attributes }}>
      @if ($vacio !== null)<option value="">{{ $vacio }}</option>@endif
      @foreach ($opciones as $clave => $texto)
        <option value="{{ $clave }}" @selected((string) $actual === (string) $clave)>{{ $texto }}</option>
      @endforeach
    </select>
  @elseif ($tipo === 'textarea')
    <textarea class="form-control @error($nombre) is-invalid @enderror" id="{{ $id }}" name="{{ $nombre }}" @required($requerido) {{ $attributes }}>{{ $actual }}</textarea>
  @else
    <input class="form-control @error($nombre) is-invalid @enderror" id="{{ $id }}" name="{{ $nombre }}" type="{{ $tipo }}" value="{{ $tipo === 'password' ? '' : $actual }}" @required($requerido) {{ $attributes }} />
  @endif
  @if ($ayuda)<span class="form-hint">{{ $ayuda }}</span>@endif
  @error($nombre)<span class="form-error">{{ $message }}</span>@else<span class="form-error" hidden></span>@enderror
</div>
