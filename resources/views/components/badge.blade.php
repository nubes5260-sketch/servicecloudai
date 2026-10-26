{{-- badge() del mockup. Acepta un enum con label()/badge() o un texto (color por el mapa del mockup o explícito). --}}
@props(['valor' => null, 'color' => null])
@php
    $esEnum = $valor instanceof \BackedEnum && method_exists($valor, 'badge');
    $texto = $esEnum ? $valor->label() : (string) ($valor ?? '—');
    $colorBadge = $color ?? ($esEnum ? $valor->badge() : \App\Support\ColorBadge::de($texto));
@endphp
<span {{ $attributes->merge(['class' => 'badge badge-'.$colorBadge]) }}>{{ $texto }}</span>
