<?php

namespace App\Http\Requests;

use App\Enums\EstadoServicio;
use App\Models\Servicio;
use Illuminate\Validation\Rule;

class GuardarServicioRequest extends FormularioTenant
{
    protected ?string $parametroModelo = 'servicio';

    public function authorize(): bool
    {
        $servicio = $this->modeloEditado();

        return $servicio instanceof Servicio ? $this->user()->can('update', $servicio) : $this->user()->can('create', Servicio::class);
    }

    public function rules(): array
    {
        return [
            'organizacion_id' => $this->reglaOrganizacion(),
            'nombre' => ['required', 'string', 'max:180'],
            'categoria' => ['nullable', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'responsable_id' => ['nullable', 'integer', $this->existeEnOrganizacion('usuarios')],
            'contrato_id' => ['nullable', 'integer', $this->existeEnOrganizacion('contratos')],
            'politica_sla_id' => ['nullable', 'integer', $this->existeEnOrganizacion('politicas_sla')],
            'disponibilidad_objetivo' => ['nullable', 'numeric', 'between:0,100'],
            'estado' => ['required', Rule::in(EstadoServicio::valores())],
            'activos' => ['nullable', 'array'],
            'activos.*' => ['integer', $this->existeEnOrganizacion('activos')],
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'servicio', 'categoria' => 'categoría', 'descripcion' => 'descripción', 'responsable_id' => 'owner',
            'contrato_id' => 'contrato', 'politica_sla_id' => 'política SLA', 'disponibilidad_objetivo' => 'disponibilidad',
            'organizacion_id' => 'organización', 'activos.*' => 'activo',
        ];
    }
}
