@extends('layouts.app')

@section('titulo', $articulo->codigo)

@section('content')
  <x-page-header :titulo="$articulo->titulo" :descripcion="$articulo->codigo.' · '.$articulo->resumen">
    <a class="btn btn-secondary" href="{{ route('conocimiento.index') }}"><i data-lucide="arrow-left"></i> Volver</a>
    @can('delete', $articulo)
      <button class="btn btn-danger" type="button" data-confirmar="¿Deseas eliminar {{ $articulo->codigo }}?" data-accion="{{ route('conocimiento.destroy', $articulo) }}"><i data-lucide="trash-2"></i> Eliminar</button>
    @endcan
    @can('update', $articulo)
      <button class="btn btn-primary" type="button" data-modal-url="{{ route('conocimiento.edit', $articulo) }}" data-modal-titulo="Editar artículo"><i data-lucide="pencil"></i> Editar</button>
    @endcan
  </x-page-header>

  <div class="grid ticket-detail-layout">
    <section class="card ticket-main">
      <div class="ticket-meta" style="margin-top:0">
        <span class="badge badge-blue">{{ $articulo->categoria }}</span>
        <x-badge :valor="$articulo->estado" />
        <x-badge :valor="$articulo->visibilidad" />
        @foreach ($articulo->etiquetas as $etiqueta)<span class="badge badge-gray">{{ $etiqueta->nombre }}</span>@endforeach
      </div>
      <div class="ticket-desc" style="white-space:pre-line;font-size:13px">{{ $articulo->contenido }}</div>
    </section>
    <aside class="card ai-panel ticket-ai">
      <div class="ai-label"><i data-lucide="book-open-check"></i> Ficha del artículo</div>
      <div class="ai-class"><span>Organización</span><b>{{ $articulo->organizacion?->razon_social ?? 'Pública (plataforma)' }}</b></div>
      <div class="ai-class"><span>Autor</span><b>{{ $articulo->autor?->nombre ?? '—' }}</b></div>
      <div class="ai-class"><span>Vistas · Versión</span><b>{{ number_format($articulo->vistas) }} · v{{ $articulo->version }}</b></div>
      <div class="ai-class"><span>Actualizado</span><b>{{ App\Support\Formato::fechaHora($articulo->actualizado_en) }}</b></div>
      <div class="status-chip">
        <b>{{ $articulo->estado->label() }}</b>
        <span>Visibilidad: {{ $articulo->visibilidad->label() }}</span>
      </div>
    </aside>
  </div>
@endsection
