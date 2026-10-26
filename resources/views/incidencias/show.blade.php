<x-modal-detalle
  :titulo="'Incidencias · '.$incidencia->codigo"
  :editar="auth()->user()->can('update', $incidencia) ? route('incidencias.edit', $incidencia) : null">
  <div class="form-group full"><x-meta-box etiqueta="Descripción" :valor="$incidencia->descripcion" /></div>
  <x-meta-box etiqueta="Organización" :valor="$incidencia->organizacion?->razon_social" />
  <x-meta-box etiqueta="Servicio" :valor="$incidencia->servicio?->nombre" />
  <x-meta-box etiqueta="Impacto"><x-badge :valor="$incidencia->impacto" /></x-meta-box>
  <x-meta-box etiqueta="Urgencia"><x-badge :valor="$incidencia->urgencia" /></x-meta-box>
  <x-meta-box etiqueta="Prioridad"><x-badge :valor="$incidencia->prioridad" /></x-meta-box>
  <x-meta-box etiqueta="Estado"><x-badge :valor="$incidencia->estado" /></x-meta-box>
  <x-meta-box etiqueta="Responsable" :valor="$incidencia->responsable?->nombre" />
  <x-meta-box etiqueta="Inicio" :valor="App\Support\Formato::fechaHora($incidencia->inicio_en)" />
  <x-meta-box etiqueta="Ticket">
    @if ($incidencia->ticket && auth()->user()->can('view', $incidencia->ticket))<a class="table-link" href="{{ route('tickets.show', $incidencia->ticket) }}">{{ $incidencia->ticket->codigo }}</a>@else{{ $incidencia->ticket?->codigo ?? '—' }}@endif
  </x-meta-box>
  <x-meta-box etiqueta="Problema" :valor="$incidencia->problema ? $incidencia->problema->codigo.' · '.$incidencia->problema->titulo : null" />
  <x-meta-box etiqueta="Resuelta" :valor="App\Support\Formato::fechaHora($incidencia->resuelta_en)" />
</x-modal-detalle>
