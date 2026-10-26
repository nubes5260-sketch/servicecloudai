@extends('layouts.app')

@section('content')
  <x-page-header :titulo="config('servicecloud.menu.'.$clave.'.titulo')" :descripcion="config('servicecloud.menu.'.$clave.'.descripcion')" />
  <div class="card">
    <x-empty-state :icono="config('servicecloud.menu.'.$clave.'.icono')" titulo="Módulo en construcción" :texto="'Este módulo se habilita en la fase '.$fase.' del plan de implementación.'" />
  </div>
@endsection
