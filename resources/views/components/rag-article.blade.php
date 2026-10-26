@props(['icono' => 'book-open', 'titulo', 'subtitulo' => '', 'href' => null])
@if ($href)
  <a class="rag-article" href="{{ $href }}"><i data-lucide="{{ $icono }}"></i><div><b>{{ $titulo }}</b><span>{{ $subtitulo }}</span></div></a>
@else
  <div class="rag-article"><i data-lucide="{{ $icono }}"></i><div><b>{{ $titulo }}</b><span>{{ $subtitulo }}</span></div></div>
@endif
