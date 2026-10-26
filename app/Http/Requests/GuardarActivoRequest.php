<?php

namespace App\Http\Requests;

use App\Enums\EstadoActivo;
use App\Enums\TipoActivo;
use App\Models\Activo;
use Illuminate\Validation\Rule;

class GuardarActivoRequest extends FormularioTenant
{
    protected ?string $parametroModelo = 'activo';

    public function authorize(): bool
    {
        $activo = $this->modeloEditado();

        return $activo instanceof Activo ? $this->user()->can('update', $activo) : $this->user()->can('create', Activo::class);
    }

    public function rules(): array
    {
        return [
            'organizacion_id' => $this->reglaOrganizacion(),
            'nombre' => ['required', 'string', 'max:180'],
            'tipo' => ['required', Rule::in(TipoActivo::valores())],
            'estado' => ['required', Rule::in(EstadoActivo::valores())],
            'ip' => ['nullable', 'string', 'max:45'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'numero_serie' => ['nullable', 'string', 'max:120'],
            'fabricante' => ['nullable', 'string', 'max:120'],
            'modelo' => ['nullable', 'string', 'max:120'],
            'ubicacion' => ['nullable', 'string', 'max:180'],
            'proveedor_cloud' => ['nullable', 'string', 'max:100'],
            'region_cloud' => ['nullable', 'string', 'max:100'],
            'responsable_id' => ['nullable', 'integer', $this->existeEnOrganizacion('usuarios')],
            'servicios' => ['nullable', 'array'],
            'servicios.*' => ['integer', $this->existeEnOrganizacion('servicios')],
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre del activo', 'ip' => 'IP', 'numero_serie' => 'número de serie', 'ubicacion' => 'ubicación',
            'proveedor_cloud' => 'proveedor cloud', 'region_cloud' => 'región cloud', 'responsable_id' => 'responsable',
            'organizacion_id' => 'organización', 'servicios.*' => 'servicio',
        ];
    }
}
