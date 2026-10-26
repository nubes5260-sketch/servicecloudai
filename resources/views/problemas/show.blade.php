<x-modal-detalle
  :titulo="'Problemas · '.$problema->codigo"
  :editar="auth()->user()->can('update', $problema) ? route('problemas.edit', $problema) : null">
  <div class="form-group full"><x-meta-box etiqueta="Problema" :valor="$problema->titulo" /></div>
  <x-meta-box etiqueta="Organización" :valor="$problema->organizacion?->razon_social" />
  <x-meta-box etiqueta="Servicio" :valor="$problema->servicio?->nombre" />
  <x-meta-box etiqueta="Responsable" :valor="$problema->responsable?->nombre" />
  <x-meta-box etiqueta="Estado"><x-badge :valor="$problema->estado" /></x-meta-box>
  <div class="form-group full"><x-meta-box etiqueta="Causa raíz" :valor="$problema->causa_raiz" /></div>
  <x-meta-box etiqueta="Workaround" :valor="$problema->solucion_temporal" />
  <x-meta-box etiqueta="Solución definitiva" :valor="$problema->solucion_definitiva" />
  <x-meta-box etiqueta="Tickets relacionados" :valor="$problema->tickets->pluck('codigo')->join(', ')" />
  <x-meta-box etiqueta="Incidencias" :valor="$problema->incidencias->pluck('codigo')->join(', ')" />
  <x-meta-box etiqueta="Creado" :valor="App\Support\Formato::fechaHora($problema->creado_en)" />
  <x-meta-box etiqueta="Cerrado" :valor="App\Support\Formato::fechaHora($problema->cerrado_en)" />
</x-modal-detalle>
