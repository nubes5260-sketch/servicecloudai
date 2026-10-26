<?php

namespace App\Http\Requests;

use App\Enums\EstadoContrato;
use App\Models\Contrato;
use Illuminate\Validation\Rule;

class GuardarContratoRequest extends FormularioTenant
{
    protected ?string $parametroModelo = 'contrato';

    public function authorize(): bool
    {
        $contrato = $this->modeloEditado();

        return $contrato instanceof Contrato ? $this->user()->can('update', $contrato) : $this->user()->can('create', Contrato::class);
    }

    public function rules(): array
    {
        return [
            'organizacion_id' => $this->reglaOrganizacion(),
            'nombre' => ['required', 'string', 'max:180'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'monto' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'estado' => ['required', Rule::in(EstadoContrato::valores())],
            'politica_sla_id' => ['nullable', 'integer', $this->existeEnOrganizacion('politicas_sla')],
            'descripcion' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'contrato', 'fecha_inicio' => 'inicio', 'fecha_fin' => 'fin', 'monto' => 'valor',
            'politica_sla_id' => 'política SLA', 'descripcion' => 'descripción', 'organizacion_id' => 'organización',
        ];
    }
}
