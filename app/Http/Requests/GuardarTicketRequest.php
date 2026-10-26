<?php

namespace App\Http\Requests;

use App\Enums\Impacto;
use App\Enums\OrigenTicket;
use App\Enums\Prioridad;
use App\Enums\Rol as RolCodigo;
use App\Enums\TipoTicket;
use App\Enums\Urgencia;
use App\Models\Rol;
use App\Models\Ticket;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class GuardarTicketRequest extends FormularioTenant
{
    protected ?string $parametroModelo = 'ticket';

    public const MIMES_ADJUNTO = 'pdf,png,jpg,jpeg,gif,webp,txt,log,csv,doc,docx,xls,xlsx,zip';

    public function authorize(): bool
    {
        $ticket = $this->modeloEditado();

        return $ticket instanceof Ticket ? $this->user()->can('update', $ticket) : $this->user()->can('create', Ticket::class);
    }

    public function rules(): array
    {
        $ticket = $this->modeloEditado();
        $usuario = $this->usuario();
        $puedeAsignar = $usuario->can('asignar', Ticket::class);
        $puedePriorizar = $ticket ? $usuario->can('priorizar', $ticket) : $puedeAsignar;
        $esSolicitante = ! $puedeAsignar && ! $usuario->tieneRol(RolCodigo::Tecnico);

        $reglas = [
            'organizacion_id' => $this->reglaOrganizacion(),
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['required', 'string', 'max:10000'],
            'tipo' => ['required', Rule::in(TipoTicket::valores())],
            'categoria_id' => ['nullable', 'integer', $this->existeEnOrganizacion('categorias_tickets')],
            'servicio_id' => ['nullable', 'integer', $this->existeEnOrganizacion('servicios')],
            'activo_id' => ['nullable', 'integer', $this->existeEnOrganizacion('activos')],
            'impacto' => ['required', Rule::in(Impacto::valores())],
            'urgencia' => ['required', Rule::in(Urgencia::valores())],
            'prioridad' => $puedePriorizar ? ['nullable', Rule::in(Prioridad::valores())] : ['prohibited'],
            'tecnico_id' => $puedeAsignar ? ['nullable', 'integer', $this->reglaTecnico()] : ['prohibited'],
        ];

        if (! $ticket) {
            $reglas['solicitante_id'] = $esSolicitante ? ['prohibited'] : ['nullable', 'integer', $this->existeEnOrganizacion('usuarios')];
            $reglas['origen'] = $esSolicitante ? ['prohibited'] : ['nullable', Rule::in(OrigenTicket::valores())];
            $reglas['adjunto'] = ['nullable', 'file', 'max:10240', 'mimes:'.self::MIMES_ADJUNTO];
        }

        return $reglas;
    }

    /** El técnico debe tener rol TECNICO en la misma organización del ticket. */
    private function reglaTecnico(): Exists
    {
        return Rule::exists('usuarios', 'id')
            ->where('organizacion_id', $this->organizacionId() ?? 0)
            ->where(fn ($q) => $q->whereExists(fn ($r) => $r->from('usuarios_roles')
                ->whereColumn('usuarios_roles.usuario_id', 'usuarios.id')
                ->where('usuarios_roles.rol_id', Rol::idDe(RolCodigo::Tecnico))));
    }

    public function attributes(): array
    {
        return [
            'titulo' => 'título', 'descripcion' => 'descripción', 'categoria_id' => 'categoría', 'servicio_id' => 'servicio',
            'activo_id' => 'activo', 'tecnico_id' => 'técnico', 'solicitante_id' => 'solicitante', 'organizacion_id' => 'organización',
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'tecnico_id.exists' => 'El técnico elegido no pertenece a la organización o no tiene rol de técnico.',
            'adjunto.max' => 'El adjunto no puede superar los 10 MB.',
        ];
    }
}
