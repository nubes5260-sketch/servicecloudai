<?php

namespace App\Http\Requests;

use App\Enums\EstadoIncidencia;
use App\Enums\Impacto;
use App\Enums\Prioridad;
use App\Enums\Urgencia;
use App\Models\Incidencia;
use Illuminate\Validation\Rule;

class GuardarIncidenciaRequest extends FormularioTenant
{
    protected ?string $parametroModelo = 'incidencia';

    public function authorize(): bool
    {
        $incidencia = $this->modeloEditado();

        return $incidencia instanceof Incidencia ? $this->user()->can('update', $incidencia) : $this->user()->can('create', Incidencia::class);
    }

    public function rules(): array
    {
        $gestion = $this->user()->can('gestionar', Incidencia::class);

        return [
            'organizacion_id' => $this->reglaOrganizacion(),
            'descripcion' => ['required', 'string', 'max:2000'],
            'servicio_id' => ['nullable', 'integer', $this->existeEnOrganizacion('servicios')],
            'impacto' => ['required', Rule::in(Impacto::valores())],
            'urgencia' => ['required', Rule::in(Urgencia::valores())],
            'prioridad' => ['nullable', Rule::in(Prioridad::valores())],
            'estado' => ['required', Rule::in(EstadoIncidencia::valores())],
            'responsable_id' => $gestion ? ['nullable', 'integer', $this->existeEnOrganizacion('usuarios')] : ['prohibited'],
            'ticket_id' => $gestion ? ['nullable', 'integer', $this->existeEnOrganizacion('tickets'),
                Rule::unique('incidencias', 'ticket_id')->ignore($this->modeloEditado()?->id)] : ['prohibited'],
            'problema_id' => $gestion ? ['nullable', 'integer', $this->existeEnOrganizacion('problemas')] : ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return ['descripcion' => 'descripción', 'servicio_id' => 'servicio', 'responsable_id' => 'responsable', 'ticket_id' => 'ticket', 'problema_id' => 'problema', 'organizacion_id' => 'organización'];
    }

    public function messages(): array
    {
        return parent::messages() + ['ticket_id.unique' => 'Ese ticket ya está vinculado a otra incidencia.'];
    }
}
