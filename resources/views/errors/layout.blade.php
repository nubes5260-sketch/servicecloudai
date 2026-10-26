@extends('layouts.guest')

@section('titulo', $titulo)

@section('content')
  <section class="card auth-card" style="text-align:center">
    <div class="auth-brand" style="justify-content:center">
      <div class="brand-mark"><i data-lucide="cloud-cog"></i></div>
      <div class="brand-copy" style="text-align:left">
        <div class="brand-title">ServiceCloud AI</div>
        <div class="brand-sub">IT Service Management</div>
      </div>
    </div>
    <div class="empty" style="padding:18px 0 8px">
      <i data-lucide="{{ $icono }}"></i>
      <b>{{ $codigo }} · {{ $titulo }}</b>
      <p>{{ $texto }}</p>
    </div>
    <a class="btn btn-primary" href="{{ auth()->check() ? route('dashboard') : route('login') }}"><i data-lucide="arrow-left"></i> {{ auth()->check() ? 'Volver al dashboard' : 'Ir al inicio de sesión' }}</a>
  </section>
@endsection
