@if ($paginator->hasPages())
  <nav class="pagination" aria-label="Paginación">
    <span>Mostrando {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }}</span>
    <div class="pagination-links">
      <a class="btn btn-secondary btn-sm {{ $paginator->onFirstPage() ? 'disabled' : '' }}" href="{{ $paginator->previousPageUrl() }}" title="Anterior"><i data-lucide="chevron-left"></i></a>
      @foreach ($elements as $element)
        @if (is_string($element))
          <span class="btn btn-ghost btn-sm disabled">{{ $element }}</span>
        @endif
        @if (is_array($element))
          @foreach ($element as $pagina => $url)
            <a class="btn btn-secondary btn-sm {{ $pagina == $paginator->currentPage() ? 'active' : '' }}" href="{{ $url }}">{{ $pagina }}</a>
          @endforeach
        @endif
      @endforeach
      <a class="btn btn-secondary btn-sm {{ $paginator->hasMorePages() ? '' : 'disabled' }}" href="{{ $paginator->nextPageUrl() }}" title="Siguiente"><i data-lucide="chevron-right"></i></a>
    </div>
  </nav>
@endif
