{{-- Formulario para el modal genérico: se envía por fetch (data-ajax) y pinta errores por campo. --}}
@props(['titulo', 'accion', 'metodo' => 'POST', 'boton' => 'Guardar', 'icono' => 'save', 'id' => 'formModal', 'archivos' => false])
<div data-modal-titulo="{{ $titulo }}">
  <div data-modal-body>
    <form id="{{ $id }}" class="form-grid" method="POST" action="{{ $accion }}" data-ajax novalidate @if ($archivos) enctype="multipart/form-data" @endif>
      @csrf
      @if (strtoupper($metodo) !== 'POST') @method($metodo) @endif
      {{ $slot }}
    </form>
  </div>
  <div data-modal-footer>
    <button class="btn btn-secondary" type="button" data-cerrar-modal>Cancelar</button>
    <button class="btn btn-primary" type="submit" form="{{ $id }}"><i data-lucide="{{ $icono }}"></i> {{ $boton }}</button>
  </div>
</div>
