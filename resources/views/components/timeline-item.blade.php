@props(['icono', 'titulo', 'texto' => ''])
<div class="timeline-item"><div class="timeline-icon"><i data-lucide="{{ $icono }}"></i></div><div><b>{{ $titulo }}</b><p>{{ $texto }}</p></div></div>
