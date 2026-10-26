<?php

namespace App\Http\Requests;

use App\Enums\EstadoProblema;
use App\Models\Problema;
use Illuminate\Validation\Rule;

class GuardarProblemaRequest extends FormularioTenant
{
    protected ?string $parametroModelo = 'problema';

    public function authorize(): bool
    {
        $problema = $this->modeloEditado();

        return $problema instanceof Problema ? $this->user()->can('update', $problema) : $this->user()->can('create', Problema::class);
    }

    public function rules(): array
    {
        $gestion = $this->user()->can('gestionar', Problema::class);

        return [
            'organizacion_id' => $this->reglaOrganizacion(),
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'servicio_id' => ['nullable', 'integer', $this->existeEnOrganizacion('servicios')],
            'responsable_id' => $gestion ? ['nullable', 'integer', $this->existeEnOrganizacion('usuarios')] : ['prohibited'],
            'causa_raiz' => ['nullable', 'string', 'max:5000'],
            'solucion_temporal' => ['nullable', 'string', 'max:5000'],
            'solucion_definitiva' => ['nullable', 'string', 'max:5000'],
            'estado' => ['required', Rule::in(EstadoProblema::valores())],
            'tickets' => ['nullable', 'array'],
            'tickets.*' => ['integer', $this->existeEnOrganizacion('tickets')],
        ];
    }

    public function attributes(): array
    {
        return [
            'titulo' => 'problema', 'descripcion' => 'descripción', 'servicio_id' => 'servicio', 'responsable_id' => 'responsable',
            'causa_raiz' => 'causa raíz', 'solucion_temporal' => 'workaround', 'solucion_definitiva' => 'solución definitiva',
            'organizacion_id' => 'organización', 'tickets.*' => 'ticket',
        ];
    }
}
