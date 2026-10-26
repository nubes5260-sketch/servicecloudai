@props(['titulo' => 'Sin resultados', 'texto' => '', 'icono' => 'search-x'])
<div {{ $attributes->merge(['class' => 'empty', 'style' => 'grid-column:1/-1']) }}><i data-lucide="{{ $icono }}"></i><b>{{ $titulo }}</b><p>{{ $texto }}</p></div>
