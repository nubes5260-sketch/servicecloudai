<?php

namespace App\Http\Requests;

use App\Enums\HorarioServicio;
use App\Enums\Prioridad;
use App\Models\PoliticaSla;
use Illuminate\Validation\Rule;

/** Política SLA con sus 4 reglas (una por prioridad). El Admin SaaS puede crear plantillas globales (sin organización). */
class GuardarPoliticaSlaRequest extends FormularioTenant
{
    protected ?string $parametroModelo = 'politica';

    public function authorize(): bool
    {
        $politica = $this->modeloEditado();

        return $politica instanceof PoliticaSla ? $this->user()->can('update', $politica) : $this->user()->can('create', PoliticaSla::class);
    }

    public function rules(): array
    {
        $editada = $this->modeloEditado();
        $reglas = [
            'organizacion_id' => ! $editada && $this->usuario()->esAdminSaas()
                ? ['nullable', 'integer', Rule::exists('organizaciones', 'id')]
                : ['prohibited'],
            'nombre' => ['required', 'string', 'max:100', Rule::unique('politicas_sla', 'nombre')
                ->where(fn ($q) => $this->organizacionId() ? $q->where('organizacion_id', $this->organizacionId()) : $q->whereNull('organizacion_id'))
                ->ignore($editada?->id)],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'activo' => ['required', 'boolean'],
            'horario_servicio' => ['required', Rule::in(HorarioServicio::valores())],
        ];
        foreach (Prioridad::valores() as $prioridad) {
            $reglas["reglas.$prioridad.respuesta"] = ['required', 'integer', 'min:1', 'max:100000'];
            $reglas["reglas.$prioridad.resolucion"] = ['required', 'integer', "gte:reglas.$prioridad.respuesta", 'max:200000'];
        }

        return $reglas;
    }

    public function attributes(): array
    {
        $atributos = ['horario_servicio' => 'horario', 'descripcion' => 'descripción', 'organizacion_id' => 'organización'];
        foreach (Prioridad::cases() as $p) {
            $atributos["reglas.{$p->value}.respuesta"] = 'respuesta ('.$p->label().')';
            $atributos["reglas.{$p->value}.resolucion"] = 'resolución ('.$p->label().')';
        }

        return $atributos;
    }

    public function messages(): array
    {
        return parent::messages() + ['reglas.*.resolucion.gte' => 'La resolución debe ser mayor o igual que la respuesta.'];
    }
}
