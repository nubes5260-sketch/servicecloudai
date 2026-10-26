<x-modal-form
  :titulo="$ticket->tecnico ? 'Reasignar '.$ticket->codigo : 'Asignar '.$ticket->codigo"
  :accion="route('tickets.reasignar', $ticket)"
  metodo="PATCH"
  :boton="$ticket->tecnico ? 'Reasignar' : 'Asignar'"
  icono="user-check"
  id="formReasignar">
  <p class="form-hint form-intro" style="grid-column:1/-1">
    @if ($ticket->tecnico)
      Hoy lo atiende <b>{{ $ticket->tecnico->nombre }}</b>. Elige a quién pasarlo: ambos técnicos y el usuario recibirán un aviso.
    @else
      Elige quién atenderá este ticket. Te sugerimos al técnico con menor carga.
    @endif
  </p>
  <div class="form-group full" data-campo="tecnico_id">
    <label>Carga actual del equipo <span class="muted" style="font-weight:400">· límite sugerido {{ $maximo }} tickets activos</span></label>
    <div class="opciones-tecnico">
      @forelse ($tecnicos as $tecnico)
        @php
            $esActual = $tecnico['id'] === $ticket->tecnico_id;
            $esRecomendado = $tecnico['id'] === ($recomendado['id'] ?? null);
            $porcentaje = min(100, (int) round($tecnico['activos'] / max(1, $maximo) * 100));
        @endphp
        <label class="opcion-tecnico {{ $esActual ? 'is-actual' : '' }}">
          <input type="radio" name="tecnico_id" value="{{ $tecnico['id'] }}" @checked($esRecomendado) @disabled($esActual) />
          <span class="opcion-tecnico-avatar">{{ $tecnico['iniciales'] }}</span>
          <span class="opcion-tecnico-info">
            <b>{{ $tecnico['nombre'] }}
              @if ($esActual)<em class="badge badge-gray">Actual</em>@endif
              @if ($esRecomendado)<em class="badge badge-green">Recomendado</em>@endif
              @if ($tecnico['alta_carga'])<em class="badge badge-red">Alta carga</em>@endif
            </b>
            <span class="barra-carga {{ $tecnico['alta_carga'] ? 'is-alta' : ($porcentaje >= 60 ? 'is-media' : '') }}"><i style="width:{{ $porcentaje }}%"></i></span>
            <small>{{ $tecnico['activos'] }} {{ $tecnico['activos'] === 1 ? 'ticket activo' : 'tickets activos' }} · {{ $tecnico['urgentes'] }} {{ $tecnico['urgentes'] === 1 ? 'urgente' : 'urgentes' }}</small>
          </span>
        </label>
      @empty
        <p class="form-hint">No hay técnicos activos en la organización.</p>
      @endforelse
    </div>
    <span class="form-error" hidden></span>
  </div>
  <x-form-field nombre="motivo" etiqueta="Motivo (opcional, queda en el historial)" tipo="textarea" completo placeholder="Ej.: María tiene 9 tickets activos; Kevin conoce mejor el ERP." rows="2" />
</x-modal-form>
