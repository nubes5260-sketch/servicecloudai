@php
    $moduloActivo = $menu->search(fn ($item) => request()->routeIs($item['ruta'] === 'dashboard' ? 'dashboard' : \Illuminate\Support\Str::before($item['ruta'], '.').'.*'));
    $tituloModulo = $moduloActivo ? $menu[$moduloActivo]['etiqueta'] : 'ServiceCloud AI';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#f4f5f7" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>@yield('titulo', $tituloModulo) — ServiceCloud AI</title>
  <link rel="stylesheet" href="{{ asset('css/servicecloud.css') }}?v={{ filemtime(public_path('css/servicecloud.css')) }}" />
  <link rel="stylesheet" href="{{ asset('css/servicecloud-claro.css') }}?v={{ filemtime(public_path('css/servicecloud-claro.css')) }}" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
  <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
</head>
<body>
  <script>
    // Recordar el sidebar contraído entre páginas (preferencia local del navegador)
    try { if (localStorage.getItem('sc_sidebar') === 'mini' && window.innerWidth > 1024) document.body.classList.add('sidebar-collapsed'); } catch (e) {}
  </script>
  <div class="app-shell">
    <aside class="sidebar" id="sidebar">
      <div class="brand">
        <div class="brand-mark"><i data-lucide="cloud-cog"></i></div>
        <div class="brand-copy">
          <div class="brand-title">ServiceCloud AI</div>
          <div class="brand-sub">IT Service Management</div>
        </div>
      </div>
      <div class="nav-section-label">Operación</div>
      <nav id="mainNav">
        @foreach ($menu as $clave => $item)
          <a href="{{ route($item['ruta']) }}" class="nav-item {{ $moduloActivo === $clave ? 'active' : '' }} {{ $clave === 'configuracion' ? 'nav-item-config' : '' }}" title="{{ $item['etiqueta'] }}">
            <i data-lucide="{{ $item['icono'] }}"></i><span>{{ $item['etiqueta'] }}</span>
            @if ($clave === 'notificaciones')<em class="nav-count" data-notif-count @if ($notificacionesNoLeidas === 0) hidden @endif>{{ $notificacionesNoLeidas }}</em>@endif
          </a>
        @endforeach
      </nav>
      <div class="sidebar-footer">
        <form method="POST" action="{{ route('logout') }}" class="sidebar-logout">
          @csrf
          <button type="submit" class="nav-item"><i data-lucide="log-out"></i><span>Cerrar sesión</span></button>
        </form>
          <div style="font-size: 0.75rem; color: #888; text-align: center; margin-top: 1rem; padding-bottom: 0.5rem;">
            &copy; {{ date('Y') }} ServiceCloud AI
        </div>
      </div>
    </aside>

    <div class="mobile-overlay" id="mobileOverlay"></div>

    <div class="main-wrap">
      <header class="header">
        <button class="icon-btn" id="menuToggle" title="Contraer menú"><i data-lucide="panel-left-close"></i></button>
        <div class="breadcrumb">
          <div class="eyebrow">ServiceCloud AI</div>
          <strong id="breadcrumbTitle">{{ $tituloModulo }}</strong>
        </div>
        <div class="header-spacer"></div>
        <div class="search-wrap">
          <div class="search-box">
            <i data-lucide="search"></i>
            <input id="globalSearch" placeholder="Buscar tickets, usuarios, activos..." autocomplete="off" data-url="{{ route('buscar') }}" />
          </div>
          <div id="searchResults" class="search-results hidden"></div>
        </div>
        <div style="position:relative">
          <button class="icon-btn" id="notifBtn" title="Notificaciones" aria-label="Notificaciones" data-url="{{ route('notificaciones.resumen') }}" data-ultima="{{ $notificacionesHeader->max('id') ?? 0 }}"><i data-lucide="bell"></i>@if ($notificacionesNoLeidas > 0)<span class="badge-dot badge-count" data-notif-count>{{ $notificacionesNoLeidas > 99 ? '99+' : $notificacionesNoLeidas }}</span>@else<span class="badge-dot badge-count" data-notif-count hidden></span>@endif</button>
          <div id="notifDropdown" class="dropdown-panel notif-dropdown hidden">
            @include('partials.notificaciones-dropdown')
          </div>
        </div>
        <div style="position:relative">
          <div class="user-block" id="profileBtn">
            <div class="avatar">{{ $usuarioActual->iniciales() }}</div>
            <div class="user-meta"><b>{{ $usuarioActual->nombre }}</b><span>{{ $usuarioActual->rolPrincipal()?->label() }}</span></div>
            <i data-lucide="chevron-down" style="width:14px"></i>
          </div>
          <div id="profileDropdown" class="dropdown-panel profile-dropdown hidden">
            <div class="profile-drop-head"><b>{{ $usuarioActual->nombre }}</b><span>{{ $usuarioActual->rolPrincipal()?->label() }}@if ($usuarioActual->organizacion) · {{ $usuarioActual->organizacion->razon_social }}@endif</span></div>
            <a class="drop-action" href="{{ route('configuracion.index') }}"><i data-lucide="settings"></i> Configuración</a>
            <form method="POST" action="{{ route('logout') }}" class="inline-form">
              @csrf
              <button type="submit" class="drop-action"><i data-lucide="log-out"></i> Cerrar sesión</button>
            </form>
          </div>
        </div>
      </header>
      <main class="content" id="mainContent">
        @yield('content')
      </main>
    </div>
  </div>

  <div class="modal-backdrop" id="modalBackdrop">
    <div class="modal">
      <div class="modal-head">
        <h3 id="modalTitle">Modal</h3>
        <button class="icon-btn" id="modalClose" type="button"><i data-lucide="x"></i></button>
      </div>
      <div class="modal-body" id="modalBody"></div>
      <div class="modal-footer" id="modalFooter"></div>
    </div>
  </div>

  <div class="toast-stack" id="toastStack">
    @include('partials.toasts')
  </div>

  @stack('templates')
  <script src="{{ asset('js/servicecloud.js') }}?v={{ filemtime(public_path('js/servicecloud.js')) }}"></script>
  @stack('scripts')
</body>
</html>
