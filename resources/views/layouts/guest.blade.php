<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#f4f5f7" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>@yield('titulo', 'Iniciar sesión') — ServiceCloud AI</title>
  <link rel="stylesheet" href="{{ asset('css/servicecloud.css') }}?v={{ filemtime(public_path('css/servicecloud.css')) }}" />
  <link rel="stylesheet" href="{{ asset('css/servicecloud-claro.css') }}?v={{ filemtime(public_path('css/servicecloud-claro.css')) }}" />
  <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
</head>
<body>
  <main class="auth-shell">
    @yield('content')
  </main>
  <div class="toast-stack" id="toastStack">
    @include('partials.toasts')
  </div>
  <script>
    if (window.lucide) lucide.createIcons();
    document.querySelectorAll('[data-auto-cerrar]').forEach(el => setTimeout(() => el.remove(), 3400));
  </script>
</body>
</html>
