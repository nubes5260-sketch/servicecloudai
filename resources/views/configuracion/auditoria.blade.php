@extends('layouts.app')

@section('titulo', 'Auditoría')

@section('content')
  <x-page-header titulo="Auditoría" descripcion="Registro de creación, edición y eliminación en organizaciones, usuarios, contratos, SLA y tickets.">
    <a class="btn btn-secondary" href="{{ route('configuracion.index') }}"><i data-lucide="arrow-left"></i> Volver</a>
  </x-page-header>

  <form class="toolbar" method="GET" data-filtros>
    <select class="field" name="entidad">
      <option value="">Todas las entidades</option>
      @foreach ($entidades as $entidad)<option value="{{ $entidad }}" @selected(request('entidad') === $entidad)>{{ $entidad }}</option>@endforeach
    </select>
    <select class="field" name="accion">
      <option value="">Todas las acciones</option>
      @foreach (['crear' => 'Crear', 'actualizar' => 'Actualizar', 'eliminar' => 'Eliminar'] as $valor => $etiqueta)
        <option value="{{ $valor }}" @selected(request('accion') === $valor)>{{ $etiqueta }}</option>
      @endforeach
    </select>
  </form>

  <div class="card table-card"><div class="table-scroll"><table>
    <thead><tr><th>Fecha</th><th>Organización</th><th>Usuario</th><th>Entidad</th><th>ID</th><th>Acción</th><th>Cambios</th><th>IP</th></tr></thead>
    <tbody>
      @forelse ($registros as $registro)
        <tr>
          <td>{{ App\Support\Formato::fechaHora($registro->fecha) }}</td>
          <td>{{ $registro->organizacion?->razon_social ?? '—' }}</td>
          <td>{{ $registro->usuario?->nombre ?? 'Sistema' }}</td>
          <td>{{ $registro->entidad }}</td>
          <td>{{ $registro->entidad_id }}</td>
          <td><x-badge :valor="ucfirst($registro->accion)" :color="['crear' => 'green', 'actualizar' => 'blue', 'eliminar' => 'red'][$registro->accion] ?? 'gray'" /></td>
          <td style="max-width:380px">
            @foreach (array_slice(array_keys($registro->datos_despues ?? $registro->datos_antes ?? []), 0, 6) as $campo)
              <span class="badge badge-gray" title="{{ json_encode(['antes' => $registro->datos_antes[$campo] ?? null, 'después' => $registro->datos_despues[$campo] ?? null], JSON_UNESCAPED_UNICODE) }}">{{ $campo }}</span>
            @endforeach
          </td>
          <td>{{ $registro->ip_origen ?? '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="8"><x-empty-state icono="history" titulo="Sin registros" texto="No hay cambios que coincidan con los filtros." /></td></tr>
      @endforelse
    </tbody>
  </table></div>
  {{ $registros->links() }}
  </div>
@endsection
