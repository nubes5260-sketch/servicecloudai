@extends('layouts.app')

@section('content')
  <x-page-header :titulo="config('servicecloud.menu.asignaciones.titulo')" :descripcion="config('servicecloud.menu.asignaciones.descripcion')">
    @can('asignar', App\Models\Ticket::class)
      <form method="POST" action="{{ route('asignaciones.autoasignar') }}" class="inline-form">
        @csrf
        <button class="btn btn-primary" id="autoAssign" type="submit"><i data-lucide="wand-sparkles"></i> Asignar pendientes por carga</button>
      </form>
    @endcan
  </x-page-header>

  @if ($equipo !== null)
    <div class="card card-pad carga-equipo" id="cargaEquipo">
      <div class="card-title">
        <div><h3>Carga del equipo</h3><span>
          @if ($configuracionCarga['automatica'])
            Los tickets nuevos se asignan solos al técnico con menos tickets activos · límite sugerido {{ $configuracionCarga['maximo'] }}
          @else
            Asignación automática desactivada: asigna los tickets nuevos manualmente
          @endif
        </span></div>
        @can('configurar-organizacion')<a class="table-link" href="{{ route('configuracion.index') }}">Ajustar reglas</a>@endcan
      </div>
      <div class="carga-lista">
        @forelse ($equipo as $tecnico)
          @php($porcentaje = min(100, (int) round($tecnico['activos'] / max(1, $configuracionCarga['maximo']) * 100)))
          <div class="carga-item {{ $tecnico['alta_carga'] ? 'is-alta' : '' }}">
            <div class="opcion-tecnico-avatar">{{ $tecnico['iniciales'] }}</div>
            <div class="carga-info">
              <b>{{ $tecnico['nombre'] }}</b>
              <span class="barra-carga {{ $tecnico['alta_carga'] ? 'is-alta' : ($porcentaje >= 60 ? 'is-media' : '') }}"><i style="width:{{ $porcentaje }}%"></i></span>
              <small>{{ $tecnico['activos'] }} activos · {{ $tecnico['urgentes'] }} urgentes</small>
            </div>
            @if ($tecnico['alta_carga'])<span class="badge badge-red">Alta carga</span>@elseif ($tecnico['activos'] === 0)<span class="badge badge-green">Disponible</span>@endif
          </div>
        @empty
          <p class="form-hint">No hay técnicos activos en la organización.</p>
        @endforelse
      </div>
    </div>
  @endif

  <div class="kanban" id="kanban" data-url="{{ url('/asignaciones') }}">
    @foreach ($columnas as $clave => $columna)
      <div class="kanban-col" data-stage="{{ $clave }}">
        <div class="kanban-head"><b>{{ $columna['titulo'] }}</b><span class="kanban-count">{{ $columna['tickets']->count() }}</span></div>
        <div class="kanban-list">
          @foreach ($columna['tickets'] as $ticket)
            @php($estadoSla = $sla->estadoSla($ticket))
            <div class="kanban-card" @can('mover', $ticket) draggable="true" @else style="cursor:default" @endcan data-id="{{ $ticket->codigo }}" data-href="{{ route('tickets.show', $ticket) }}">
              <x-badge :valor="$ticket->prioridad" />
              <h4>{{ \Illuminate\Support\Str::limit($ticket->titulo, 70) }}</h4>
              <p>{{ $ticket->codigo }}</p>
              <div class="kanban-meta">
                <span class="muted {{ in_array($estadoSla['tipo'], ['vencido', 'riesgo'], true) ? 'texto-riesgo' : '' }}" style="font-size:9px">
                  @switch($estadoSla['tipo'])
                    @case('cumplido') SLA cumplido @break
                    @case('incumplido') SLA incumplido @break
                    @case('vencido') SLA vencido @break
                    @case('pausa') SLA en pausa @break
                    @default SLA activo · {{ $estadoSla['texto'] }}
                  @endswitch
                </span>
                <div class="kanban-acciones">
                  @can('asignar', $ticket)
                    <button class="icon-mini" type="button" title="{{ $ticket->tecnico ? 'Reasignar' : 'Asignar' }}" data-modal-url="{{ route('tickets.reasignar.form', $ticket) }}" data-modal-titulo="{{ $ticket->tecnico ? 'Reasignar' : 'Asignar' }} {{ $ticket->codigo }}"><i data-lucide="{{ $ticket->tecnico ? 'users' : 'user-plus' }}"></i></button>
                  @endcan
                  <div class="mini-avatar" title="{{ $ticket->tecnico?->nombre ?? 'Sin asignar' }}">{{ $ticket->tecnico?->iniciales() ?? '--' }}</div>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    @endforeach
  </div>

  @push('templates')
    <template id="tplResolver" data-titulo="Resolver ticket">
      <div data-modal-body>
        <form id="formResolver" class="form-grid" data-resolver novalidate>
          <div class="form-group full" data-campo="solucion">
            <label for="f_solucion_kanban">Solución aplicada</label>
            <textarea class="form-control" id="f_solucion_kanban" name="solucion" required placeholder="Describe qué se hizo para resolver el ticket..."></textarea>
            <span class="form-error" hidden></span>
          </div>
        </form>
      </div>
      <div data-modal-footer>
        <button class="btn btn-secondary" type="button" data-cerrar-modal>Cancelar</button>
        <button class="btn btn-primary" type="submit" form="formResolver"><i data-lucide="circle-check-big"></i> Marcar como resuelto</button>
      </div>
    </template>
  @endpush

  @push('scripts')
    <script>
      // setupDragDrop() del mockup: PATCH /asignaciones/{ticket} con CSRF; "Resuelto" pide la solución.
      (() => {
        const { $, $$, scFetch, scToast, openModalDesdeHTML, closeModal } = SC;
        const kanban = $('#kanban');
        let arrastrado = null;

        async function mover(codigo, columna, solucion = null) {
          const r = await scFetch(`${kanban.dataset.url}/${encodeURIComponent(codigo)}`, { method: 'PATCH', body: { columna, solucion } });
          if (r.ok && r.data?.redirect) { window.location.href = r.data.redirect; return true; }
          const errores = r.data?.errors ? Object.values(r.data.errors).flat() : [];
          scToast('No se pudo mover', errores[0] || r.data?.message || 'Inténtalo nuevamente.', 'error');
          return false;
        }

        $$('.kanban-card').forEach(card => {
          card.addEventListener('dragstart', () => { arrastrado = card.dataset.id; card.style.opacity = '.45'; });
          card.addEventListener('dragend', () => { card.style.opacity = '1'; arrastrado = null; $$('.kanban-col').forEach(c => c.classList.remove('drag-over')); });
          card.addEventListener('click', e => { if (!e.target.closest('button')) window.location.href = card.dataset.href; });
        });
        $$('.kanban-col').forEach(col => {
          col.addEventListener('dragover', e => { e.preventDefault(); col.classList.add('drag-over'); });
          col.addEventListener('dragleave', () => col.classList.remove('drag-over'));
          col.addEventListener('drop', e => {
            e.preventDefault();
            col.classList.remove('drag-over');
            const codigo = arrastrado;
            if (!codigo) return;
            if (col.dataset.stage === 'resuelto') {
              const tpl = $('#tplResolver');
              const div = document.createElement('div'); div.appendChild(tpl.content.cloneNode(true));
              openModalDesdeHTML(div.innerHTML, `Resolver ${codigo}`);
              $('#modalBody form[data-resolver]').addEventListener('submit', async ev => {
                ev.preventDefault();
                const solucion = ev.target.solucion.value.trim();
                if (!solucion) { scToast('Falta la solución', 'Describe qué se hizo para resolverlo.', 'error'); return; }
                if (await mover(codigo, 'resuelto', solucion)) closeModal();
              });
              return;
            }
            mover(codigo, col.dataset.stage);
          });
        });
      })();
    </script>
  @endpush
@endsection
