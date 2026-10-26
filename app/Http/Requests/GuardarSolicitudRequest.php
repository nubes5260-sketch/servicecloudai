<?php

namespace App\Http\Requests;

use App\Enums\Prioridad;
use App\Enums\Rol;
use App\Models\Solicitud;
use Illuminate\Validation\Rule;

class GuardarSolicitudRequest extends FormularioTenant
{
    protected ?string $parametroModelo = 'solicitud';

    public function authorize(): bool
    {
        $solicitud = $this->modeloEditado();

        return $solicitud instanceof Solicitud ? $this->user()->can('update', $solicitud) : $this->user()->can('create', Solicitud::class);
    }

    public function rules(): array
    {
        $esSolicitante = ! $this->usuario()->esAdminSaas() && ! $this->usuario()->tieneRol(Rol::AdminOrganizacion, Rol::Supervisor);

        return [
            'organizacion_id' => $this->reglaOrganizacion(),
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'prioridad' => ['required', Rule::in(Prioridad::valores())],
            'solicitante_id' => $esSolicitante || $this->modeloEditado() ? ['prohibited'] : ['nullable', 'integer', $this->existeEnOrganizacion('usuarios')],
        ];
    }

    public function attributes(): array
    {
        return ['titulo' => 'solicitud', 'descripcion' => 'descripción', 'solicitante_id' => 'solicitante', 'organizacion_id' => 'organización'];
    }
}
