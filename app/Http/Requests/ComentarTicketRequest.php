<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ComentarTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('comentar', $this->route('ticket'));
    }

    public function rules(): array
    {
        return [
            'contenido' => ['required_without:adjunto', 'nullable', 'string', 'max:5000'],
            'es_interno' => ['nullable', 'boolean'],
            'adjunto' => ['nullable', 'file', 'max:10240', 'mimes:'.GuardarTicketRequest::MIMES_ADJUNTO],
        ];
    }

    /** Las notas internas solo las pueden crear técnicos y gestión. */
    public function esInterno(): bool
    {
        return $this->boolean('es_interno') && $this->user()->can('verInternos', $this->route('ticket'));
    }

    public function attributes(): array
    {
        return ['contenido' => 'mensaje'];
    }

    public function messages(): array
    {
        return ['contenido.required_without' => 'Escribe un mensaje o adjunta un archivo.', 'adjunto.max' => 'El adjunto no puede superar los 10 MB.'];
    }
}
