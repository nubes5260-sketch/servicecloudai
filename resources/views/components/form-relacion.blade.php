{{-- Select de registros relacionados. Cada opción lleva data-org para filtrar por la organización elegida (Admin SaaS). --}}
@props(['nombre', 'etiqueta', 'registros', 'campo' => 'nombre', 'valor' => null, 'multiple' => false, 'vacio' => 'Sin asignar', 'requerido' => false, 'completo' => false, 'ayuda' => null])
@php
    $campoError = $multiple ? $nombre.'.*' : $nombre;
    $actual = collect(old($nombre, $valor instanceof \Illuminate\Support\Collection ? $valor->all() : $valor))->map(fn ($v) => (string) $v)->all();
    $id = 'f_'.$nombre;
@endphp
<div class="form-group {{ $completo ? 'full' : '' }}" data-campo="{{ $nombre }}">
  <label for="{{ $id }}">{{ $etiqueta }}</label>
  <select class="form-control @error($nombre) is-invalid @enderror @error($campoError) is-invalid @enderror" id="{{ $id }}" name="{{ $nombre }}{{ $multiple ? '[]' : '' }}" @if ($multiple) multiple size="4" @endif @required($requerido) {{ $attributes }}>
    @unless ($multiple)<option value="">{{ $vacio }}</option>@endunless
    @foreach ($registros as $registro)
      <option value="{{ $registro->id }}" data-org="{{ $registro->organizacion_id }}" @selected(in_array((string) $registro->id, $actual, true))>{{ is_callable($campo) ? $campo($registro) : $registro->{$campo} }}</option>
    @endforeach
  </select>
  @if ($ayuda)<span class="form-hint">{{ $ayuda }}</span>@endif
  @if ($errors->has($nombre) || $errors->has($campoError))
    <span class="form-error">{{ $errors->first($nombre) ?: $errors->first($campoError) }}</span>
  @else
    <span class="form-error" hidden></span>
  @endif
</div>
