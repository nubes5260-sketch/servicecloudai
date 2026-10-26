@extends('layouts.guest')

@section('content')
  <section class="card auth-card">
    <div class="auth-brand">
      <div class="brand-mark"><i data-lucide="cloud-cog"></i></div>
      <div class="brand-copy">
        <div class="brand-title">ServiceCloud AI</div>
        <div class="brand-sub">IT Service Management</div>
      </div>
    </div>

    <h1>Iniciar sesión</h1>
    <p class="auth-sub">Accede a la mesa de ayuda de tu organización.</p>

    <form method="POST" action="{{ route('login') }}" class="auth-form" novalidate>
      @csrf
      @error('email')
        <div class="auth-alert" role="alert"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div>
      @enderror

      <div class="form-group">
        <label for="email">Correo</label>
        <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus />
      </div>
      <div class="form-group">
        <label for="password">Contraseña</label>
        <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" autocomplete="current-password" required />
        @error('password')<span class="form-error">{{ $message }}</span>@enderror
      </div>

      <button type="submit" class="btn btn-primary"><i data-lucide="log-in"></i> Ingresar</button>
    </form>

    <div class="auth-footer">
      <span><i data-lucide="shield-check" style="width:12px;height:12px;vertical-align:-2px"></i> Conexión segura</span>
      <span>Gestión de servicios de TI</span>
    </div>
  </section>
@endsection
