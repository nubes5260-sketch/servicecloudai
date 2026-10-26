@php
    // Para quien reporta: preguntas en lenguaje simple en lugar de términos ITIL
    $simple = $esSolicitante && ! $ticket->exists;
    $opcionesTipo = $simple
        ? ['incidente' => 'Algo dejó de funcionar o da error', 'solicitud' => 'Necesito algo nuevo (acceso, equipo, instalación)', 'consulta' => 'Tengo una duda', 'cambio' => 'Quiero pedir un cambio']
        : App\Enums\TipoTicket::opciones();
    $opcionesImpacto = $simple ? ['bajo' => 'Solo a mí', 'medio' => 'A mi equipo o área', 'alto' => 'A toda la empresa o a clientes'] : App\Enums\Impacto::opciones();
    $opcionesUrgencia = $simple ? ['baja' => 'Puedo seguir trabajando', 'media' => 'Me retrasa, pero hay alternativa', 'alta' => 'No puedo trabajar'] : App\Enums\Urgencia::opciones();
@endphp
<x-modal-form
  :titulo="$ticket->exists ? 'Editar ticket' : ($simple ? 'Reportar un problema' : 'Nuevo ticket')"
  :accion="$ticket->exists ? route('tickets.update', $ticket) : route('tickets.store')"
  :metodo="$ticket->exists ? 'PUT' : 'POST'"
  :boton="$simple ? 'Enviar ticket' : 'Guardar'"
  :icono="$simple ? 'send' : 'save'"
  :archivos="! $ticket->exists">
  @if ($simple)
    <p class="form-hint form-intro" style="grid-column:1/-1">Cuéntanos qué pasa. Te avisaremos cuando un técnico lo tome y podrás seguir el avance desde «Mis tickets».</p>
  @endif
  @if ($organizaciones)
    <x-form-field nombre="organizacion_id" etiqueta="Organización" :opciones="$organizaciones" vacio="Selecciona una organización" requerido completo />
  @endif
  <x-form-field nombre="titulo" :etiqueta="$simple ? '¿Qué problema tienes?' : 'Título'" :valor="$ticket->titulo" requerido completo placeholder="Ej.: La aplicación demora demasiado y muestra error al consultar clientes" />
  <x-form-field nombre="descripcion" :etiqueta="$simple ? 'Cuéntanos más' : 'Descripción'" tipo="textarea" :valor="$ticket->descripcion" requerido completo placeholder="Qué ocurre, desde cuándo, a quiénes afecta y qué mensaje aparece." :ayuda="$simple ? 'Mientras más detalle (mensaje de error, hora, pasos), más rápido lo resolvemos.' : null" />
  <x-form-field nombre="tipo" :etiqueta="$simple ? '¿Qué necesitas?' : 'Tipo'" :valor="$ticket->tipo" :opciones="$opcionesTipo" requerido :completo="$simple" />
  <x-form-relacion nombre="categoria_id" :etiqueta="$simple ? 'Tema (opcional)' : 'Categoría'" :registros="$categorias" :valor="$ticket->categoria_id" :vacio="$simple ? 'No sé (soporte lo clasificará)' : 'Sin categoría'" />
  <x-form-relacion nombre="servicio_id" :etiqueta="$simple ? 'Sistema afectado (opcional)' : 'Servicio'" :registros="$servicios" :valor="$ticket->servicio_id" :vacio="$simple ? 'No sé / otro' : 'Sin servicio'" />
  @unless ($simple)
    <x-form-relacion nombre="activo_id" etiqueta="Activo" :registros="$activos" :valor="$ticket->activo_id" :campo="fn ($a) => $a->codigo.' · '.$a->nombre" vacio="Sin activo" />
  @endunless
  <x-form-field nombre="impacto" :etiqueta="$simple ? '¿A quiénes afecta?' : 'Impacto'" :valor="$ticket->impacto" :opciones="$opcionesImpacto" requerido />
  <x-form-field nombre="urgencia" :etiqueta="$simple ? '¿Qué tan urgente es?' : 'Urgencia'" :valor="$ticket->urgencia" :opciones="$opcionesUrgencia" requerido />
  @if ($puedePriorizar)
    <x-form-field nombre="prioridad" etiqueta="Prioridad" :valor="$ticket->exists ? $ticket->prioridad : null" :opciones="App\Enums\Prioridad::opciones()" vacio="Automática (impacto × urgencia)" />
  @elseif (! $simple)
    <x-meta-box etiqueta="Prioridad" valor="Se calcula por impacto × urgencia" />
  @endif
  @if ($puedeAsignar)
    <x-form-relacion nombre="tecnico_id" etiqueta="Técnico" :registros="$tecnicos" :valor="$ticket->tecnico_id" vacio="Sin asignar" />
  @endif
  @unless ($ticket->exists || $esSolicitante)
    <x-form-relacion nombre="solicitante_id" etiqueta="Solicitante" :registros="$solicitantes" vacio="Yo mismo" />
    <x-form-field nombre="origen" etiqueta="Canal" :opciones="App\Enums\OrigenTicket::opciones()" valor="portal" />
  @endunless
  @unless ($ticket->exists)
    <x-form-field nombre="adjunto" :etiqueta="$simple ? 'Captura o archivo (opcional, máx. 10 MB)' : 'Adjunto (opcional, máx. 10 MB)'" tipo="file" completo />
  @endunless
</x-modal-form>
