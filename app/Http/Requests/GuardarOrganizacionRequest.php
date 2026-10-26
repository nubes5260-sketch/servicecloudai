<?php

namespace App\Http\Requests;

use App\Enums\EstadoOrganizacion;
use App\Models\Organizacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Crear y editar organizaciones (solo ADMIN_SAAS; lo verifica la Policy). */
class GuardarOrganizacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organizacion = $this->route('organizacion');

        return $organizacion instanceof Organizacion
            ? $this->user()->can('update', $organizacion)
            : $this->user()->can('create', Organizacion::class);
    }

    public function rules(): array
    {
        $id = $this->route('organizacion')?->id;

        return [
            'razon_social' => ['required', 'string', 'max:180'],
            'ruc' => ['nullable', 'digits:11', Rule::unique('organizaciones', 'ruc')->ignore($id)],
            'sector' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:180'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'plan_id' => ['required', 'integer', Rule::exists('planes_saas', 'id')],
            'estado' => ['required', Rule::in(EstadoOrganizacion::valores())],
        ];
    }

    public function attributes(): array
    {
        return ['razon_social' => 'organización', 'ruc' => 'RUC', 'plan_id' => 'plan', 'telefono' => 'teléfono', 'direccion' => 'dirección'];
    }
}
