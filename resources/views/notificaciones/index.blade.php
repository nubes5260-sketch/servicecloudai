@extends('layouts.app')

@section('content')
  <x-page-header :titulo="config('servicecloud.menu.notificaciones.titulo')" :descripcion="config('servicecloud.menu.notificaciones.descripcion')">
    <a class="btn btn-secondary" href="{{ route('notificaciones.index', request('filtro') === 'no_leidas' ? [] : ['filtro' => 'no_leidas']) }}">
      <i data-lucide="{{ request('filtro') === 'no_leidas' ? 'list' : 'filter' }}"></i> {{ request('filtro') === 'no_leidas' ? 'Ver todas' : 'Solo no leídas' }}
    </a>
    <form method="POST" action="{{ route('notificaciones.marcar-todas') }}" class="inline-form">
      @csrf
      <button class="btn btn-secondary" id="markAll" type="submit"><i data-lucide="check-check"></i> Marcar todo como leído</button>
    </form>
  </x-page-header>

  <div class="card card-pad">
    @forelse ($notificaciones as $notificacion)
      @php([$iconoNotif, $tonoNotif] = $notificacion->apariencia())
      <a class="notif-item notif-fila {{ $notificacion->leida_en ? '' : 'unread' }}" href="{{ route('notificaciones.abrir', $notificacion) }}" data-notif="{{ $notificacion->id }}">
        <div class="notif-icono tono-{{ $tonoNotif }}"><i data-lucide="{{ $iconoNotif }}"></i></div>
        <div class="result-text" style="flex:1"><b>{{ $notificacion->titulo }}</b><span>{{ $notificacion->mensaje }}</span><small>{{ App\Support\Formato::relativo($notificacion->creado_en) }}@if ($notificacion->ticket) · {{ $notificacion->ticket->codigo }}@endif</small></div>
        @unless ($notificacion->leida_en)<span class="notif-nuevo" title="Sin leer"></span>@endunless
        <x-badge :valor="$notificacion->tipo" />
      </a>
    @empty
      <x-empty-state icono="bell-off" titulo="Sin notificaciones" texto="Aquí verás alertas de SLA, asignaciones y cambios operativos." />
    @endforelse
    {{ $notificaciones->links() }}
  </div>
@endsection
