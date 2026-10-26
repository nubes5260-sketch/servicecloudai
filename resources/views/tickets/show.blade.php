@extends('layouts.app')

@section('titulo', $ticket->codigo)

@php
    use App\Enums\EstadoTicket;
    use App\Support\Formato;
    $usuario = auth()->user();
    $abierto = ! in_array($ticket->estado, [EstadoTicket::Cerrado, EstadoTicket::Cancelado], true);
    $puedeComentar = $abierto && $usuario->can('comentar', $ticket);
    $plazo = $ticket->vencimiento_resolucion;

    // Botones del "siguiente paso": [texto, ícono, clase, tipo, destino]
    $botones = [
        'asignar' => ['Asignar técnico', 'user-plus', 'btn-primary', 'modal', route('tickets.reasignar.form', $ticket)],
        'reasignar' => ['Reasignar', 'users', 'btn-secondary', 'modal', route('tickets.reasignar.form', $ticket)],
        'solicitar_reasignacion' => ['Pedir reasignación', 'hand', 'btn-secondary', 'plantilla', '#tplSolicitarReasignacion'],
        'iniciar' => ['Iniciar atención', 'play', 'btn-primary', 'estado', 'en_progreso'],
        'reanudar' => ['Reanudar atención', 'play', 'btn-primary', 'estado', 'en_progreso'],
        'resolver' => ['Marcar como resuelto', 'circle-check-big', 'btn-primary', 'plantilla', '#tplResolver'],
        'pedir_info' => ['Pedir información al usuario', 'message-circle-question', 'btn-secondary', 'plantilla', '#tplPedirInfo'],
        'cerrar' => ['Cerrar ticket', 'archive', 'btn-primary', 'estado', 'cerrado'],
        'reabrir' => ['Reabrir', 'rotate-ccw', 'btn-secondary', 'plantilla', '#tplReabrir'],
        'cancelar' => ['Cancelar ticket', 'circle-slash', 'btn-ghost', 'plantilla', '#tplCancelar'],
    ];
    // "Iniciar" en el primer botón primario; el resto pasa a secundario
    $primeraPrimaria = true;
@endphp

@section('content')
  <x-page-header :titulo="$vistaSolicitante ? 'Mi ticket' : 'Detalle de ticket'" :descripcion="$vistaSolicitante ? $ticket->codigo.' · Sigue aquí el avance de tu solicitud y conversa con el técnico.' : $ticket->codigo.' · Gestión y seguimiento del incidente.'">
    <a class="btn btn-secondary" id="backTickets" href="{{ route('tickets.index') }}"><i data-lucide="arrow-left"></i> Volver</a>
    @can('update', $ticket)
      <button class="btn btn-secondary" type="button" id="ticketEdit" data-modal-url="{{ route('tickets.edit', $ticket) }}" data-modal-titulo="Editar ticket"><i data-lucide="pencil"></i> Editar</button>
    @endcan
  </x-page-header>

  <div class="grid ticket-detail-layout">
    <section class="card ticket-main" data-ticket-actual="{{ $ticket->codigo }}">
      <div class="card-title">
        <div><span>Ticket {{ $ticket->codigo }} · {{ $ticket->solicitante?->nombre }}</span><h3 class="ticket-title">{{ $ticket->titulo }}</h3></div>
        <x-badge :valor="$ticket->estado" />
      </div>
      <div class="ticket-meta">
        <x-badge :valor="$ticket->prioridad" />
        <span class="badge badge-cyan">{{ $ticket->nombreCategoria() ?? 'Sin categoría' }}</span>
        <x-badge :valor="$ticket->tipo" />
        @unless ($vistaSolicitante)
          @if ($ticket->incidencia)<span class="badge badge-purple">{{ $ticket->incidencia->codigo }}</span>@endif
          @foreach ($ticket->problemas as $problema)<span class="badge badge-yellow">{{ $problema->codigo }}</span>@endforeach
        @endunless
      </div>

      <x-progreso-ticket :pasos="$pasos" />

      {{-- ¿Qué está pasando? (solicitante) / ¿Qué hago ahora? (técnico y gestión) --}}
      @if ($vistaSolicitante)
        <div class="estado-banner tono-{{ $mensajeSolicitante['tono'] }}" id="estadoBanner">
          <div class="estado-banner-icono"><i data-lucide="{{ $mensajeSolicitante['icono'] }}"></i></div>
          <div class="estado-banner-texto">
            <b>{{ $mensajeSolicitante['titulo'] }}</b>
            <p>{{ $mensajeSolicitante['texto'] }}</p>
            @if ($ticket->estado === EstadoTicket::Resuelto && $ticket->solucion)
              <div class="estado-solucion"><span>Solución aplicada</span><p>{{ $ticket->solucion }}</p></div>
            @endif
            @can('responderSolucion', $ticket)
              <div class="estado-banner-acciones">
                <form method="POST" action="{{ route('tickets.confirmacion', $ticket) }}" data-ajax id="formConfirmarSolucion" class="inline-form">
                  @csrf @method('PATCH')
                  <input type="hidden" name="decision" value="confirmar" />
                  <button class="btn btn-primary" type="submit"><i data-lucide="thumbs-up"></i> Sí, quedó resuelto</button>
                </form>
                <button class="btn btn-secondary" type="button" data-modal-template="#tplSigueFallando"><i data-lucide="thumbs-down"></i> No, sigue fallando</button>
              </div>
            @endcan
            @if ($ticket->estado === EstadoTicket::EnEspera)
              <div class="estado-banner-acciones">
                <a class="btn btn-primary" href="#respuesta" data-enfocar="#mensajeNuevo"><i data-lucide="reply"></i> Responder ahora</a>
              </div>
            @endif
          </div>
        </div>
      @elseif ($siguientePaso)
        <div class="siguiente-paso" id="siguientePaso">
          <div class="siguiente-paso-head">
            <span class="siguiente-paso-eyebrow"><i data-lucide="navigation"></i> Siguiente paso</span>
            <b>{{ $siguientePaso['titulo'] }}</b>
            <p>{{ $siguientePaso['texto'] }}</p>
          </div>
          @if ($siguientePaso['acciones'])
            <div class="siguiente-paso-acciones">
              @foreach ($siguientePaso['acciones'] as $accion)
                @php
                    [$texto, $icono, $clase, $tipo, $destino] = $botones[$accion];
                    if ($clase === 'btn-primary') { $clase = $primeraPrimaria ? 'btn-primary' : 'btn-secondary'; $primeraPrimaria = false; }
                @endphp
                @switch($tipo)
                  @case('estado')
                    <form method="POST" action="{{ route('tickets.estado', $ticket) }}" data-ajax class="inline-form" id="accion_{{ $accion }}">
                      @csrf @method('PATCH')
                      <input type="hidden" name="estado" value="{{ $destino }}" />
                      <button class="btn {{ $clase }}" type="submit" data-accion-ticket="{{ $accion }}"><i data-lucide="{{ $icono }}"></i> {{ $texto }}</button>
                    </form>
                    @break
                  @case('plantilla')
                    <button class="btn {{ $clase }}" type="button" data-modal-template="{{ $destino }}" data-accion-ticket="{{ $accion }}"><i data-lucide="{{ $icono }}"></i> {{ $texto }}</button>
                    @break
                  @case('modal')
                    <button class="btn {{ $clase }}" type="button" data-modal-url="{{ $destino }}" data-modal-titulo="{{ $texto }}" data-accion-ticket="{{ $accion }}"><i data-lucide="{{ $icono }}"></i> {{ $texto }}</button>
                    @break
                @endswitch
              @endforeach
            </div>
          @endif
        </div>
      @endif

      <div class="bloque-titulo">{{ $vistaSolicitante ? 'Lo que reportaste' : 'Descripción' }}</div>
      <p class="ticket-desc" style="white-space:pre-line;margin-top:6px">{{ $ticket->descripcion }}</p>

      <div class="meta-grid">
        @if ($vistaSolicitante)
          <x-meta-box etiqueta="Técnico" :valor="$ticket->tecnico?->nombre ?? 'Por asignar'" />
          <x-meta-box etiqueta="Servicio" :valor="$ticket->servicio?->nombre ?? 'No indicado'" />
          <x-meta-box etiqueta="Creado" :valor="Formato::fechaHora($ticket->creado_en)" />
          @if ($ticket->estado->esActivo())
            <x-meta-box etiqueta="Plazo estimado" :valor="$plazo ? Formato::fechaHora($plazo) : '—'" />
          @else
            <x-meta-box etiqueta="Resuelto" :valor="Formato::fechaHora($ticket->resuelto_en)" />
          @endif
          <x-meta-box etiqueta="Última actualización" :valor="Formato::relativo($ticket->actualizado_en ?? $ticket->creado_en)" />
          <x-meta-box etiqueta="Canal" :valor="$ticket->origen->label()" />
        @else
          <x-meta-box etiqueta="Organización" :valor="$ticket->organizacion?->razon_social" />
          <x-meta-box etiqueta="Servicio" :valor="$ticket->servicio?->nombre" />
          <x-meta-box etiqueta="Técnico" :valor="$ticket->tecnico?->nombre ?? 'Sin asignar'" />
          <x-meta-box etiqueta="SLA restante">
            @if ($estadoSla['tipo'] === 'cumplido')<x-badge valor="Cumplido" color="green" />
            @elseif ($estadoSla['tipo'] === 'incumplido')<x-badge valor="Incumplido" color="red" />
            @else<em style="font-style:normal" class="{{ in_array($estadoSla['tipo'], ['vencido', 'riesgo'], true) ? 'texto-riesgo' : '' }}">{{ $estadoSla['texto'] }}{{ $estadoSla['tipo'] === 'pausa' ? ' · en pausa' : '' }}</em>@endif
          </x-meta-box>
          <x-meta-box etiqueta="Creado" :valor="Formato::fechaHora($ticket->creado_en)" />
          <x-meta-box etiqueta="Canal" :valor="$ticket->origen->label()" />
        @endif
      </div>

      @if ($ticket->solucion && ! ($vistaSolicitante && $ticket->estado === EstadoTicket::Resuelto))
        <div class="diagnosis" style="margin-top:0"><h4>Solución</h4><p style="white-space:pre-line">{{ $ticket->solucion }}</p></div>
      @endif

      @if ($ticket->adjuntos->isNotEmpty())
        <div class="card-title" style="margin-top:16px"><h3>Adjuntos</h3><span>{{ $ticket->adjuntos->count() }}</span></div>
        <div class="rag-articles">
          @foreach ($ticket->adjuntos as $adjunto)
            <x-rag-article icono="paperclip" :titulo="$adjunto->nombre_original" :subtitulo="number_format(($adjunto->tamano_bytes ?? 0) / 1024, 0).' KB · '.Formato::fechaHora($adjunto->creado_en)" :href="route('tickets.adjuntos.descargar', [$ticket, $adjunto])" />
          @endforeach
        </div>
      @endif

      {{-- Conversación entre el usuario y el equipo (las notas internas solo las ve el equipo) --}}
      <div class="conversacion" id="respuesta">
        <div class="card-title"><h3>Conversación</h3><span>{{ $conversacion->count() }} {{ $conversacion->count() === 1 ? 'mensaje' : 'mensajes' }}</span></div>
        <div class="mensajes">
          @forelse ($conversacion as $comentario)
            @php($propio = $comentario->autor_id === $usuario->id)
            <div class="mensaje {{ $propio ? 'is-propio' : '' }} {{ $comentario->es_interno ? 'is-interno' : '' }}">
              <div class="mensaje-avatar">{{ $comentario->autor?->iniciales() ?? '?' }}</div>
              <div class="mensaje-cuerpo">
                <div class="mensaje-meta">
                  <b>{{ $propio ? 'Tú' : $comentario->autor?->nombre }}</b>
                  @if ($comentario->autor_id === $ticket->tecnico_id)<span class="mensaje-rol">Técnico</span>@elseif ($comentario->autor_id === $ticket->solicitante_id)<span class="mensaje-rol">Solicitante</span>@endif
                  @if ($comentario->es_interno)<span class="mensaje-rol is-interno"><i data-lucide="lock"></i> Nota interna</span>@endif
                  <time title="{{ Formato::fechaHora($comentario->creado_en) }}">{{ Formato::relativo($comentario->creado_en) }}</time>
                </div>
                <p>{{ $comentario->contenido }}</p>
              </div>
            </div>
          @empty
            <div class="mensajes-vacio"><i data-lucide="messages-square"></i><span>{{ $vistaSolicitante ? 'Aún no hay mensajes. Si tienes más detalles, escríbelos aquí.' : 'Aún no hay mensajes con el usuario.' }}</span></div>
          @endforelse
        </div>

        @if ($puedeComentar)
          <form class="responder" method="POST" action="{{ route('tickets.comentarios', $ticket) }}" data-ajax enctype="multipart/form-data" id="formResponder">
            @csrf
            @if ($vistaSolicitante && $ticket->estado === EstadoTicket::EnEspera)
              <div class="responder-aviso"><i data-lucide="info"></i> El técnico espera tu respuesta. Al enviarla, tu ticket vuelve a atención.</div>
            @endif
            @can('verInternos', $ticket)
              <div class="segmento" role="radiogroup" aria-label="Tipo de mensaje">
                <label><input type="radio" name="es_interno" value="0" checked /> <span><i data-lucide="message-square"></i> Mensaje al usuario</span></label>
                <label><input type="radio" name="es_interno" value="1" /> <span><i data-lucide="lock"></i> Nota interna</span></label>
              </div>
            @endcan
            <div class="form-group" data-campo="contenido">
              <label for="mensajeNuevo" class="sr-only">Mensaje</label>
              <textarea class="form-control" id="mensajeNuevo" name="contenido" rows="3" placeholder="{{ $vistaSolicitante ? 'Escribe tu mensaje para el técnico...' : 'Escribe una actualización para el usuario...' }}"></textarea>
              <span class="form-error" hidden></span>
            </div>
            <div class="responder-pie">
              <label class="adjuntar" data-campo="adjunto">
                <i data-lucide="paperclip"></i> <span data-nombre-archivo>Adjuntar archivo</span>
                <input type="file" name="adjunto" hidden />
                <span class="form-error" hidden></span>
              </label>
              <button class="btn btn-primary" type="submit" id="addNote"><i data-lucide="send"></i> Enviar</button>
            </div>
          </form>
        @endif
      </div>

      <details class="historial" @unless ($vistaSolicitante) open @endunless>
        <summary><i data-lucide="history"></i> Historial de cambios <span>{{ $actividad->count() }}</span></summary>
        <div class="timeline">
          @forelse ($actividad as $evento)
            <x-timeline-item :icono="$evento['icono']" :titulo="$evento['titulo']" :texto="$evento['texto']" />
          @empty
            <x-empty-state titulo="Sin actividad" icono="history" />
          @endforelse
        </div>
      </details>
    </section>

    @if ($vistaSolicitante)
      <aside class="card ticket-ai ticket-ayuda">
        <div class="ai-label"><i data-lucide="headset"></i> Quién te atiende</div>
        <div class="atiende">
          @if ($ticket->tecnico)
            <div class="atiende-avatar">{{ $ticket->tecnico->iniciales() }}</div>
            <div><b>{{ $ticket->tecnico->nombre }}</b><span>Técnico de soporte</span></div>
          @else
            <div class="atiende-avatar is-vacio"><i data-lucide="user-search"></i></div>
            <div><b>Asignando técnico</b><span>Te avisaremos cuando alguien lo tome.</span></div>
          @endif
        </div>
        <div class="ayuda-lista">
          <div><i data-lucide="bell-ring"></i><span>Te notificamos cada avance en la campana de arriba.</span></div>
          <div><i data-lucide="message-square"></i><span>Escribe en la conversación si tienes más información.</span></div>
          <div><i data-lucide="circle-check-big"></i><span>Cuando se resuelva, confirma si funcionó.</span></div>
        </div>

        @can('modulo', 'conocimiento')
          <a class="btn btn-secondary" style="width:100%;margin-top:12px" id="buscarConocimiento" href="{{ route('conocimiento.index') }}"><i data-lucide="book-open-check"></i> Ver la base de conocimiento</a>
        @endcan
      </aside>
    @else
      {{-- Ficha del caso para el técnico y la gestión --}}
      <aside class="card ai-panel ticket-ai" id="fichaCaso">
        <div class="ai-label"><i data-lucide="clipboard-list"></i> Ficha del caso</div>
        <div class="ai-class"><span>Prioridad</span><b class="prioridad-{{ $ticket->prioridad->value }}">{{ $ticket->prioridad->label() }}</b></div>
        <div class="ai-class"><span>Impacto · Urgencia</span><b>{{ $ticket->impacto->label() }} · {{ $ticket->urgencia->label() }}</b></div>
        <div class="ai-class"><span>Política SLA</span><b>{{ $ticket->sla?->regla?->politica?->nombre ?? 'Sin política' }}{{ $ticket->sla?->regla ? ' · '.$ticket->sla->regla->horario_servicio->label() : '' }}</b></div>
        <div class="ai-class"><span>Vence la respuesta</span><b>{{ $ticket->vencimiento_respuesta ? Formato::fechaHora($ticket->vencimiento_respuesta) : '—' }}</b></div>
        <div class="ai-class"><span>Vence la resolución</span><b>{{ $ticket->vencimiento_resolucion ? Formato::fechaHora($ticket->vencimiento_resolucion) : '—' }}</b></div>
        @if (($ticket->sla?->minutos_pausados ?? 0) > 0)
          <div class="ai-class"><span>Tiempo en pausa</span><b>{{ Formato::duracion((int) $ticket->sla->minutos_pausados) }}</b></div>
        @endif
        <div class="ai-class"><span>Activo</span><b>{{ $ticket->activo ? $ticket->activo->codigo.' · '.$ticket->activo->nombre : 'No indicado' }}</b></div>
        @can('modulo', 'conocimiento')
          <div class="card-title" style="margin-top:17px"><h3>Base de conocimiento</h3></div>
          <p class="ticket-desc" style="margin-top:0">Revisa si ya existe una solución documentada para este caso.</p>
          @php($categoriaKb = in_array($ticket->nombreCategoria(), App\Http\Controllers\ConocimientoController::CHIPS, true) ? $ticket->nombreCategoria() : null)
          <a class="btn btn-secondary" style="width:100%" id="buscarConocimiento" href="{{ route('conocimiento.index', array_filter(['categoria' => $categoriaKb])) }}"><i data-lucide="book-open-check"></i> {{ $categoriaKb ? 'Ver artículos de '.$categoriaKb : 'Buscar en la base de conocimiento' }}</a>
        @endcan
      </aside>
    @endif
  </div>

  @push('templates')
    @can('update', $ticket)
      <template id="tplResolver" data-titulo="Marcar como resuelto">
        <div data-modal-body>
          <form id="formResolver" class="form-grid" method="POST" action="{{ route('tickets.estado', $ticket) }}" data-ajax>
            @csrf @method('PATCH')
            <input type="hidden" name="estado" value="resuelto" />
            <div class="form-group full" data-campo="solucion">
              <label for="f_solucion">¿Qué se hizo para resolverlo?</label>
              <textarea class="form-control" id="f_solucion" name="solucion" required placeholder="Ej.: Se reinició el pool de conexiones y se agregó un índice a la consulta de clientes.">{{ $ticket->solucion }}</textarea>
              <span class="form-hint">El usuario verá esta solución y podrá confirmarla o indicar que sigue fallando.</span>
              <span class="form-error" hidden></span>
            </div>
          </form>
        </div>
        <div data-modal-footer>
          <button class="btn btn-secondary" type="button" data-cerrar-modal>Cancelar</button>
          <button class="btn btn-primary" type="submit" form="formResolver"><i data-lucide="circle-check-big"></i> Marcar como resuelto</button>
        </div>
      </template>

      <template id="tplPedirInfo" data-titulo="Pedir información al usuario">
        <div data-modal-body>
          <form id="formPedirInfo" class="form-grid" method="POST" action="{{ route('tickets.estado', $ticket) }}" data-ajax>
            @csrf @method('PATCH')
            <input type="hidden" name="estado" value="en_espera" />
            <input type="hidden" name="pedir_info" value="1" />
            <div class="form-group full" data-campo="comentario">
              <label for="f_pedir_info">¿Qué necesitas que te responda {{ $ticket->solicitante?->nombre ?? 'el usuario' }}?</label>
              <textarea class="form-control" id="f_pedir_info" name="comentario" required placeholder="Ej.: ¿Puedes enviarme una captura del error y la hora exacta en que ocurrió?"></textarea>
              <span class="form-hint">El ticket pasa a "En espera" y el SLA se pausa. Cuando el usuario responda, vuelve a "En progreso".</span>
              <span class="form-error" hidden></span>
            </div>
          </form>
        </div>
        <div data-modal-footer>
          <button class="btn btn-secondary" type="button" data-cerrar-modal>Cancelar</button>
          <button class="btn btn-primary" type="submit" form="formPedirInfo"><i data-lucide="send"></i> Enviar y poner en espera</button>
        </div>
      </template>

      <template id="tplReabrir" data-titulo="Reabrir ticket">
        <div data-modal-body>
          <form id="formReabrir" class="form-grid" method="POST" action="{{ route('tickets.estado', $ticket) }}" data-ajax>
            @csrf @method('PATCH')
            <input type="hidden" name="estado" value="en_progreso" />
            <div class="form-group full" data-campo="comentario">
              <label for="f_reabrir">Motivo (lo verá el usuario)</label>
              <textarea class="form-control" id="f_reabrir" name="comentario" placeholder="Ej.: Revisaremos de nuevo porque el error volvió a aparecer."></textarea>
              <span class="form-error" hidden></span>
            </div>
          </form>
        </div>
        <div data-modal-footer>
          <button class="btn btn-secondary" type="button" data-cerrar-modal>Cancelar</button>
          <button class="btn btn-primary" type="submit" form="formReabrir"><i data-lucide="rotate-ccw"></i> Reabrir</button>
        </div>
      </template>

      <template id="tplCancelar" data-titulo="Cancelar ticket">
        <div data-modal-body>
          <form id="formCancelar" class="form-grid" method="POST" action="{{ route('tickets.estado', $ticket) }}" data-ajax>
            @csrf @method('PATCH')
            <input type="hidden" name="estado" value="cancelado" />
            <div class="form-group full" data-campo="comentario">
              <label for="f_cancelar">Motivo (lo verá el usuario)</label>
              <textarea class="form-control" id="f_cancelar" name="comentario" placeholder="Ej.: Ticket duplicado de TKT-2026-00011."></textarea>
              <span class="form-error" hidden></span>
            </div>
            <p class="form-hint form-group full">Un ticket cancelado ya no se puede reabrir.</p>
          </form>
        </div>
        <div data-modal-footer>
          <button class="btn btn-secondary" type="button" data-cerrar-modal>Volver</button>
          <button class="btn btn-danger" type="submit" form="formCancelar"><i data-lucide="circle-slash"></i> Cancelar ticket</button>
        </div>
      </template>
    @endcan

    @can('solicitarReasignacion', $ticket)
      <template id="tplSolicitarReasignacion" data-titulo="Pedir reasignación">
        <div data-modal-body>
          <form id="formSolicitarReasignacion" class="form-grid" method="POST" action="{{ route('tickets.solicitar-reasignacion', $ticket) }}" data-ajax>
            @csrf
            <div class="form-group full" data-campo="motivo">
              <label for="f_motivo_reasignacion">¿Por qué necesitas que lo reasignen?</label>
              <textarea class="form-control" id="f_motivo_reasignacion" name="motivo" required placeholder="Ej.: Tengo 9 tickets activos y 3 críticos; no llegaré al plazo de este."></textarea>
              <span class="form-hint">Tu supervisor recibe el aviso con la carga del equipo y decide a quién pasarlo. Mientras tanto sigues a cargo.</span>
              <span class="form-error" hidden></span>
            </div>
          </form>
        </div>
        <div data-modal-footer>
          <button class="btn btn-secondary" type="button" data-cerrar-modal>Cancelar</button>
          <button class="btn btn-primary" type="submit" form="formSolicitarReasignacion"><i data-lucide="send"></i> Enviar solicitud</button>
        </div>
      </template>
    @endcan

    @can('responderSolucion', $ticket)
      <template id="tplSigueFallando" data-titulo="El problema continúa">
        <div data-modal-body>
          <form id="formSigueFallando" class="form-grid" method="POST" action="{{ route('tickets.confirmacion', $ticket) }}" data-ajax>
            @csrf @method('PATCH')
            <input type="hidden" name="decision" value="reabrir" />
            <div class="form-group full" data-campo="comentario">
              <label for="f_sigue_fallando">¿Qué sigue fallando?</label>
              <textarea class="form-control" id="f_sigue_fallando" name="comentario" required placeholder="Ej.: El error volvió a aparecer hoy a las 10:15 al consultar clientes."></textarea>
              <span class="form-hint">Tu ticket vuelve a atención y le avisamos a {{ $ticket->tecnico?->nombre ?? 'el técnico' }}.</span>
              <span class="form-error" hidden></span>
            </div>
          </form>
        </div>
        <div data-modal-footer>
          <button class="btn btn-secondary" type="button" data-cerrar-modal>Cancelar</button>
          <button class="btn btn-primary" type="submit" form="formSigueFallando"><i data-lucide="rotate-ccw"></i> Reabrir mi ticket</button>
        </div>
      </template>
    @endcan
  @endpush

  @push('scripts')
    <script>
      (() => {
        const form = document.getElementById('formResponder');
        if (!form) return;
        const archivo = form.querySelector('input[type=file]');
        const nombre = form.querySelector('[data-nombre-archivo]');
        archivo?.addEventListener('change', () => { nombre.textContent = archivo.files[0]?.name || 'Adjuntar archivo'; });
        const texto = form.querySelector('textarea');
        const placeholders = { 0: 'Escribe una actualización para el usuario...', 1: 'Nota solo para el equipo (el usuario no la verá)...' };
        form.querySelectorAll('input[name=es_interno]').forEach(r => r.addEventListener('change', () => {
          texto.placeholder = placeholders[r.value];
          form.classList.toggle('is-interno', r.value === '1' && r.checked);
        }));
        // Ctrl/Cmd + Enter envía
        texto?.addEventListener('keydown', e => { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) form.requestSubmit(); });
        document.querySelectorAll('[data-enfocar]').forEach(a => a.addEventListener('click', () => setTimeout(() => document.querySelector(a.dataset.enfocar)?.focus(), 250)));
        // La conversación se abre mostrando el último mensaje
        const lista = document.querySelector('.mensajes');
        if (lista) lista.scrollTop = lista.scrollHeight;
      })();
    </script>
  @endpush
@endsection
