<?php

namespace App\Http\Requests;

use App\Enums\EstadoUsuario;
use App\Enums\Rol;
use App\Models\Usuario;
use App\Services\UsuarioService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class GuardarUsuarioRequest extends FormularioTenant
{
    protected ?string $parametroModelo = 'usuario';

    public function authorize(): bool
    {
        $usuario = $this->modeloEditado();

        return $usuario instanceof Usuario
            ? $this->user()->can('update', $usuario)
            : $this->user()->can('create', Usuario::class);
    }

    public function rules(): array
    {
        $editado = $this->modeloEditado();
        $rolesPermitidos = array_keys(app(UsuarioService::class)->rolesAsignables($this->user(), $editado));
        $creaAdminSaas = ! $editado && $this->input('rol') === Rol::AdminSaas->value;

        return [
            'organizacion_id' => $creaAdminSaas ? ['nullable', 'prohibited'] : $this->reglaOrganizacion(),
            'nombre' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:180', Rule::unique('usuarios', 'email')->ignore($editado?->id)],
            'rol' => ['required', Rule::in($rolesPermitidos)],
            'cargo' => ['nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'estado' => ['required', Rule::in(EstadoUsuario::valores())],
            'password' => [$editado ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ];
    }

    public function datosConOrganizacion(): array
    {
        $datos = parent::datosConOrganizacion();
        if (! $this->modeloEditado() && $this->input('rol') === Rol::AdminSaas->value) {
            $datos['organizacion_id'] = null;
        }

        return $datos;
    }

    public function attributes(): array
    {
        return ['nombre' => 'nombre completo', 'email' => 'correo', 'password' => 'contraseña', 'organizacion_id' => 'organización', 'telefono' => 'teléfono'];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'organizacion_id.required' => 'Elige la organización del usuario.',
            'organizacion_id.prohibited' => 'Un Administrador SaaS no pertenece a una organización.',
        ];
    }
}
