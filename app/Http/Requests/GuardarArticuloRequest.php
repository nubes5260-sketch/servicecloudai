<?php

namespace App\Http\Requests;

use App\Enums\EstadoArticulo;
use App\Enums\VisibilidadArticulo;
use App\Models\ArticuloConocimiento;
use Illuminate\Validation\Rule;

class GuardarArticuloRequest extends FormularioTenant
{
    protected ?string $parametroModelo = 'articulo';

    public function authorize(): bool
    {
        $articulo = $this->modeloEditado();

        return $articulo instanceof ArticuloConocimiento ? $this->user()->can('update', $articulo) : $this->user()->can('create', ArticuloConocimiento::class);
    }

    public function rules(): array
    {
        $puedePublicar = $this->user()->can('publicar', ArticuloConocimiento::class);

        return [
            // El Admin SaaS puede crear artículos públicos de plataforma (sin organización)
            'organizacion_id' => ! $this->modeloEditado() && $this->usuario()->esAdminSaas()
                ? ['nullable', 'integer', Rule::exists('organizaciones', 'id')]
                : ['prohibited'],
            'titulo' => ['required', 'string', 'max:255'],
            'categoria' => ['required', Rule::exists('categorias_conocimiento', 'nombre')],
            'resumen' => ['required', 'string', 'max:500'],
            'contenido' => ['required', 'string', 'min:40', 'max:50000'],
            'visibilidad' => ['required', Rule::in(VisibilidadArticulo::valores())],
            'estado' => $puedePublicar ? ['required', Rule::in(EstadoArticulo::valores())] : ['nullable', Rule::in([EstadoArticulo::Borrador->value])],
            'etiquetas' => ['nullable', 'string', 'max:300'],
        ];
    }

    /** @return list<string> */
    public function etiquetas(): array
    {
        return collect(explode(',', (string) $this->input('etiquetas')))
            ->map(fn ($e) => mb_strtolower(trim($e)))->filter(fn ($e) => $e !== '' && mb_strlen($e) <= 80)
            ->unique()->take(10)->values()->all();
    }

    public function attributes(): array
    {
        return ['titulo' => 'título', 'categoria' => 'categoría', 'organizacion_id' => 'organización'];
    }
}
