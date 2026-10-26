<x-modal-detalle
  :titulo="'Contratos · '.$contrato->codigo"
  :editar="auth()->user()->can('update', $contrato) ? route('contratos.edit', $contrato) : null">
  <x-meta-box etiqueta="Contrato" :valor="$contrato->nombre" />
  <x-meta-box etiqueta="Organización" :valor="$contrato->organizacion?->razon_social" />
  <x-meta-box etiqueta="Inicio" :valor="App\Support\Formato::fecha($contrato->fecha_inicio)" />
  <x-meta-box etiqueta="Fin" :valor="App\Support\Formato::fecha($contrato->fecha_fin)" />
  <x-meta-box etiqueta="Valor" :valor="App\Support\Formato::moneda($contrato->monto)" />
  <x-meta-box etiqueta="Estado"><x-badge :valor="$contrato->estado" /></x-meta-box>
  <x-meta-box etiqueta="Política SLA">@if ($contrato->politicaSla)<x-badge :valor="$contrato->politicaSla->nombre" />@else — @endif</x-meta-box>
  <x-meta-box etiqueta="Servicios cubiertos" :valor="$contrato->servicios->pluck('nombre')->join(', ')" />
  @if ($contrato->descripcion)
    <div class="form-group full"><x-meta-box etiqueta="Descripción" :valor="$contrato->descripcion" /></div>
  @endif
</x-modal-detalle>
