<?php

namespace App\Http\Requests;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Base para formularios de tablas con organizacion_id.
 * - Usuarios de una organización: se usa siempre la suya (nunca la del formulario).
 * - Administrador SaaS: elige la organización al crear; al editar se conserva la del registro.
 * Las relaciones se validan contra esa organización (FK compuestas del esquema).
 */
abstract class FormularioTenant extends FormRequest
{
    /** Nombre del parámetro de ruta del modelo que se edita (null al crear). */
    protected ?string $parametroModelo = null;

    protected function usuario(): Usuario
    {
        return $this->user();
    }

    protected function modeloEditado(): ?Model
    {
        $modelo = $this->parametroModelo ? $this->route($this->parametroModelo) : null;

        return $modelo instanceof Model ? $modelo : null;
    }

    public function organizacionId(): ?int
    {
        if ($modelo = $this->modeloEditado()) {
            return $modelo->organizacion_id;
        }
        if (! $this->usuario()->esAdminSaas()) {
            return $this->usuario()->organizacion_id;
        }

        return $this->filled('organizacion_id') ? (int) $this->input('organizacion_id') : null;
    }

    /** Regla para elegir la organización (solo el Admin SaaS al crear). */
    protected function reglaOrganizacion(): array
    {
        if ($this->modeloEditado() || ! $this->usuario()->esAdminSaas()) {
            return ['prohibited'];
        }

        return ['required', 'integer', Rule::exists('organizaciones', 'id')];
    }

    /** exists sobre una tabla de la misma organización. */
    protected function existeEnOrganizacion(string $tabla, string $columna = 'id'): Exists
    {
        return Rule::exists($tabla, $columna)->where('organizacion_id', $this->organizacionId() ?? 0);
    }

    /** Datos validados con la organización resuelta (para crear). */
    public function datosConOrganizacion(): array
    {
        $datos = $this->validated();
        unset($datos['organizacion_id']);
        if (! $this->modeloEditado()) {
            $datos['organizacion_id'] = $this->organizacionId();
        }

        return $datos;
    }

    public function messages(): array
    {
        return [
            'organizacion_id.prohibited' => 'La organización no se puede cambiar.',
            '*.exists' => 'El valor elegido no existe o no pertenece a la organización.',
        ];
    }
}
