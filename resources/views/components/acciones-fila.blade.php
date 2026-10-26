{{-- Acciones de genericRow(): ver, editar, eliminar (cada una según permiso). --}}
@props(['ver' => null, 'editar' => null, 'eliminar' => null, 'nombre' => '', 'textoEliminar' => null])
<div class="action-group">
  @if ($ver)
    <button class="icon-mini" type="button" title="Ver detalle" data-modal-url="{{ $ver }}"><i data-lucide="eye"></i></button>
  @endif
  @if ($editar)
    <button class="icon-mini" type="button" title="Editar" data-modal-url="{{ $editar }}"><i data-lucide="pencil"></i></button>
  @endif
  @if ($eliminar)
    <button class="icon-mini" type="button" title="Eliminar" data-confirmar="{{ $textoEliminar ?? '¿Deseas eliminar '.$nombre.'? Esta acción no se puede deshacer.' }}" data-accion="{{ $eliminar }}"><i data-lucide="trash-2"></i></button>
  @endif
  {{ $slot }}
</div>
