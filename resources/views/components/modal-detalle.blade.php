{{-- Vista de detalle para el modal (openCrudView del mockup): meta-boxes + Cerrar / Editar. --}}
@props(['titulo', 'editar' => null])
<div data-modal-titulo="{{ $titulo }}">
  <div data-modal-body>
    <div class="form-grid">{{ $slot }}</div>
  </div>
  <div data-modal-footer>
    <button class="btn btn-secondary" type="button" data-cerrar-modal>Cerrar</button>
    @if ($editar)
      <button class="btn btn-primary" type="button" data-modal-url="{{ $editar }}"><i data-lucide="pencil"></i> Editar</button>
    @endif
  </div>
</div>
