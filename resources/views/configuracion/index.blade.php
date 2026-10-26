@extends('layouts.app')

@section('content')
  <x-page-header :titulo="config('servicecloud.menu.configuracion.titulo')" :descripcion="config('servicecloud.menu.configuracion.descripcion')">
    <button class="btn btn-primary" id="saveSettings" type="submit" form="formConfiguracion"><i data-lucide="save"></i> Guardar cambios</button>
  </x-page-header>

  @if ($organizaciones)
    <form class="toolbar" method="GET" data-filtros>
      <label class="muted" for="selOrganizacion" style="font-size:11px">Organización a configurar</label>
      <select class="field grow" id="selOrganizacion" name="organizacion">
        @foreach ($organizaciones as $id => $nombre)<option value="{{ $id }}" @selected($organizacion?->id === $id)>{{ $nombre }}</option>@endforeach
      </select>
    </form>
  @endif

  <form method="POST" action="{{ route('configuracion.guardar', array_filter(['organizacion' => $organizaciones ? $organizacion?->id : null])) }}" id="formConfiguracion">
    @csrf
    <div class="grid settings-grid">
      <div class="card settings-card">
        <div class="card-title"><h3>Notificaciones</h3><span>Preferencias</span></div>
        <x-setting-row titulo="Correo electrónico" texto="Alertas críticas y resúmenes por email." nombre="email_activo" :activo="$preferencias->email_activo" />
        <x-setting-row titulo="Notificaciones push" texto="Alertas en la aplicación y navegador." nombre="push_activo" :activo="$preferencias->push_activo" />
        <x-setting-row titulo="Notificaciones en la app" texto="Campana y centro de notificaciones." nombre="in_app_activo" :activo="$preferencias->in_app_activo" />
        <x-setting-row titulo="Tema claro" texto="Blancos y grises con acento azul." fijo />
      </div>

      @if ($config)
        <div class="card settings-card">
          <div class="card-title"><h3>Atención y asignación</h3><span>{{ $organizacion->razon_social }}</span></div>
          <x-setting-row titulo="Asignación automática por carga" texto="Cada ticket nuevo se asigna al técnico con menos tickets activos. El supervisor puede reasignarlo." nombre="asignacion_automatica" :activo="$config->configuracion['asignacion_automatica'] ?? true" />
          <div class="settings-row">
            <div><b>Límite de carga por técnico</b><span>Desde este número de tickets activos se marca "Alta carga" y se avisa al supervisor.</span></div>
            <select class="field" name="carga_maxima" style="min-width:110px">
              @foreach ([3, 4, 5, 6, 8, 10, 12, 15, 20] as $limite)
                <option value="{{ $limite }}" @selected(($config->configuracion['carga_maxima'] ?? App\Services\CargaTecnicos::MAXIMO_POR_DEFECTO) == $limite)>{{ $limite }} tickets</option>
              @endforeach
            </select>
          </div>
          <div class="settings-row">
            <div><b>Política SLA por defecto</b><span>Se usa cuando el servicio o el contrato no tienen política.</span></div>
            <select class="field" name="politica_sla_defecto_id" style="min-width:150px">
              <option value="">Primera activa</option>
              @foreach ($politicas as $id => $nombre)<option value="{{ $id }}" @selected(($config->configuracion['politica_sla_defecto_id'] ?? null) == $id)>{{ $nombre }}</option>@endforeach
            </select>
          </div>
          <div class="settings-row"><div><b>Zona horaria</b><span>Cálculo de horario laboral y fechas.</span></div><span class="muted" style="font-size:11px">{{ $config->zona_horaria }}</span></div>
        </div>
      @endif

      @if ($planes->isNotEmpty())
        <div class="card settings-card">
          <div class="card-title"><h3>Planes SaaS</h3><span>Plataforma</span></div>
          @foreach ($planes as $plan)
            <div class="settings-row">
              <div><b><x-badge :valor="$plan->nombre" /></b><span>{{ implode(' · ', $plan->caracteristicas ?? []) }}</span></div>
              <span class="muted" style="font-size:10px;text-align:right">{{ $plan->organizaciones_count }} org. · usuarios {{ $plan->limite_usuarios ?? '∞' }} · tickets {{ $plan->limite_tickets ?? '∞' }}</span>
            </div>
          @endforeach
        </div>
      @endif
    </div>
  </form>

  @if ($auditoria->isNotEmpty() || Gate::allows('ver-auditoria'))
    <div class="card table-card" style="margin-top:16px">
      <div class="card-title" style="padding:16px 18px 0"><h3>Auditoría reciente</h3><a class="btn btn-secondary btn-sm" href="{{ route('configuracion.auditoria') }}"><i data-lucide="history"></i> Ver todo</a></div>
      <div class="table-scroll"><table>
        <thead><tr><th>Fecha</th><th>Usuario</th><th>Entidad</th><th>ID</th><th>Acción</th><th>IP</th></tr></thead>
        <tbody>
          @forelse ($auditoria as $registro)
            <tr><td>{{ App\Support\Formato::fechaHora($registro->fecha) }}</td><td>{{ $registro->usuario?->nombre ?? 'Sistema' }}</td><td>{{ $registro->entidad }}</td><td>{{ $registro->entidad_id }}</td><td><x-badge :valor="ucfirst($registro->accion)" :color="['crear' => 'green', 'actualizar' => 'blue', 'eliminar' => 'red'][$registro->accion] ?? 'gray'" /></td><td>{{ $registro->ip_origen ?? '—' }}</td></tr>
          @empty
            <tr><td colspan="6"><x-empty-state icono="history" titulo="Sin registros" texto="Los cambios en organizaciones, usuarios, contratos, SLA y tickets aparecerán aquí." /></td></tr>
          @endforelse
        </tbody>
      </table></div>
    </div>
  @endif

  @push('scripts')
    <script>
      // Interruptores del mockup ($$('.switch').forEach(sw => sw.onclick = toggle)) que actualizan el campo oculto
      document.querySelectorAll('.switch[data-setting]').forEach(sw => sw.addEventListener('click', () => {
        const activo = sw.classList.toggle('on');
        sw.setAttribute('aria-pressed', activo ? 'true' : 'false');
        const campo = sw.parentElement.querySelector('input[type=hidden]');
        if (campo) campo.value = activo ? '1' : '0';
      }));
    </script>
  @endpush
@endsection
