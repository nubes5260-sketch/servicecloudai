<?php

namespace App\Http\Requests;

use App\Enums\EstadoTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ticket'));
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in(EstadoTicket::valores())],
            'solucion' => ['nullable', 'required_if:estado,resuelto', 'string', 'max:5000'],
            // Mensaje opcional para el solicitante (obligatorio al pedirle información)
            'comentario' => ['nullable', 'required_if:pedir_info,1', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return ['solucion' => 'solución', 'comentario' => 'mensaje'];
    }

    public function messages(): array
    {
        return [
            'solucion.required_if' => 'Describe la solución para marcar el ticket como resuelto.',
            'comentario.required_if' => 'Indica qué información necesitas del usuario.',
        ];
    }
}
