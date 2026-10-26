@extends('layouts.app')


@section('content')
  <x-page-header :titulo="config('servicecloud.menu.conocimiento.titulo')" :descripcion="config('servicecloud.menu.conocimiento.descripcion')">
    @can('create', App\Models\ArticuloConocimiento::class)
      <button class="btn btn-primary" type="button" id="newArticle" data-modal-url="{{ route('conocimiento.create') }}" data-modal-titulo="Nuevo artículo"><i data-lucide="plus"></i> Nuevo artículo</button>
    @endcan
  </x-page-header>

  <form class="toolbar" method="GET" action="{{ route('conocimiento.index') }}" id="kbForm">
    <input type="hidden" name="categoria" value="{{ $categoria }}" />
    <input class="field grow" type="search" id="kbSearch" name="q" value="{{ $q }}" placeholder="Buscar procedimientos, errores, soluciones..." />
    <button class="btn btn-secondary" type="submit" id="kbBuscar"><i data-lucide="search"></i> Buscar</button>
  </form>

  <div class="kb-cats">
    @foreach (App\Http\Controllers\ConocimientoController::CHIPS as $chip)
      <a class="cat-btn {{ $categoria === $chip ? 'active' : '' }}" href="{{ route('conocimiento.index', array_filter(['categoria' => $chip === 'Todas' ? null : $chip, 'q' => $q ?: null])) }}">{{ $chip }}</a>
    @endforeach
  </div>

  <div class="grid article-grid" id="articleGrid">
    @forelse ($articulos as $articulo)
      <a class="card article" href="{{ route('conocimiento.show', $articulo) }}" data-article="{{ $articulo->codigo }}">
        <div class="article-icon"><i data-lucide="{{ $articulo->categoriaCatalogo?->icono ?? 'book-open' }}"></i></div>
        <span class="badge badge-blue">{{ $articulo->categoria }}</span>
        @if ($articulo->estado !== App\Enums\EstadoArticulo::Publicado) <x-badge :valor="$articulo->estado" /> @endif
        @if ($articulo->visibilidad === App\Enums\VisibilidadArticulo::Interna) <x-badge :valor="$articulo->visibilidad" /> @endif
        <h3 style="margin-top:9px">{{ $articulo->titulo }}</h3>
        <p>{{ $articulo->resumen }}</p>
        <div class="article-footer">
          <span>{{ number_format($articulo->vistas) }} vistas</span>
          <span>{{ App\Support\Formato::relativo($articulo->actualizado_en) }}</span>
        </div>
      </a>
    @empty
      <x-empty-state titulo="Sin resultados" texto="Prueba con otros términos o con otra categoría." />
    @endforelse
  </div>
@endsection
