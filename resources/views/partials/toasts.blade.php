{{-- Mensajes flash como toasts: ->with('ok', 'Título') o ->with('ok', ['Título', 'Detalle']) --}}
@foreach (['ok' => ['', 'check'], 'error' => ['toast-error', 'circle-alert'], 'aviso' => ['toast-warn', 'info']] as $clave => [$clase, $icono])
  @if (session()->has($clave))
    @php([$tituloToast, $textoToast] = array_pad((array) session($clave), 2, ''))
    <div class="toast {{ $clase }}" data-auto-cerrar>
      <div class="toast-icon"><i data-lucide="{{ $icono }}"></i></div>
      <div><b>{{ $tituloToast }}</b>@if ($textoToast)<p>{{ $textoToast }}</p>@endif</div>
    </div>
  @endif
@endforeach
@if ($errors->any() && ! session()->has('error'))
  <div class="toast toast-error" data-auto-cerrar>
    <div class="toast-icon"><i data-lucide="circle-alert"></i></div>
    <div><b>Revisa los datos</b><p>{{ $errors->first() }}</p></div>
  </div>
@endif
