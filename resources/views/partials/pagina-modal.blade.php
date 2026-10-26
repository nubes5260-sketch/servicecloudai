{{-- Muestra en página completa un parcial pensado para el modal (cuando se abre su URL directamente). --}}
@extends('layouts.app')

@section('content')
  <div class="card card-pad" style="max-width:820px;margin:0 auto" id="paginaModal">
    @include($vista, $datos)
  </div>
  @push('scripts')
    <script>
      (() => {
        const raiz = document.getElementById('paginaModal');
        const titulo = raiz.querySelector('[data-modal-titulo]')?.dataset.modalTitulo;
        if (titulo) raiz.insertAdjacentHTML('afterbegin', `<div class="card-title"><h3>${SC.escapeHTML(titulo)}</h3></div>`);
        raiz.querySelectorAll('[data-modal-footer]').forEach(f => { f.className = 'modal-footer'; f.style.padding = '14px 0 0'; });
        raiz.querySelectorAll('[data-cerrar-modal]').forEach(b => b.addEventListener('click', () => history.back()));
      })();
    </script>
  @endpush
@endsection
