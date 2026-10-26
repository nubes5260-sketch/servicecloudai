<div class="notif-head">
  <div><b>Notificaciones</b><span>{{ $notificacionesNoLeidas > 0 ? $notificacionesNoLeidas.' sin leer' : 'Estás al día' }}</span></div>
  @if ($notificacionesNoLeidas > 0)
    <form method="POST" action="{{ route('notificaciones.marcar-todas') }}" class="inline-form">
      @csrf
      <button type="submit" class="notif-head-accion" title="Marcar todas como leídas"><i data-lucide="check-check"></i> Marcar leídas</button>
    </form>
  @endif
</div>
@forelse ($notificacionesHeader as $notificacion)
  @php([$iconoNotif, $tonoNotif] = $notificacion->apariencia())
  <a class="notif-item {{ $notificacion->leida_en ? '' : 'unread' }}" href="{{ route('notificaciones.abrir', $notificacion) }}">
    <div class="notif-icono tono-{{ $tonoNotif }}"><i data-lucide="{{ $iconoNotif }}"></i></div>
    <div class="result-text"><b>{{ $notificacion->titulo }}</b><span>{{ $notificacion->mensaje }}</span><small>{{ \App\Support\Formato::relativo($notificacion->creado_en) }}</small></div>
    @unless ($notificacion->leida_en)<span class="notif-nuevo" title="Sin leer"></span>@endunless
  </a>
@empty
  <div class="empty"><i data-lucide="bell-off"></i><b>Sin notificaciones</b><p>Te avisaremos cuando tu ticket avance o te necesiten.</p></div>
@endforelse
<a class="drop-action" href="{{ route('notificaciones.index') }}"><i data-lucide="list"></i> Ver todas las notificaciones</a>
