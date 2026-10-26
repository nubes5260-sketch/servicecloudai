@props(['etiqueta', 'valor' => null])
<div class="meta-box"><span>{{ $etiqueta }}</span><b>@if ($slot->isNotEmpty()){{ $slot }}@else{{ filled($valor) ? $valor : '—' }}@endif</b></div>
