@props(['titulo', 'descripcion' => ''])
<div class="page-head">
  <div class="page-title"><h1>{{ $titulo }}</h1><p>{{ $descripcion }}</p></div>
  <div class="page-actions">{{ $slot }}</div>
</div>
