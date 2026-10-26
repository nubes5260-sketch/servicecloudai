<?php

namespace App\Models;

use App\Enums\EstadoSolicitud;
use App\Enums\Prioridad;
use App\Models\Concerns\PerteneceAOrganizacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Solicitud extends Model
{
    use PerteneceAOrganizacion;

    const CREATED_AT = 'creado_en';

    const UPDATED_AT = null;

    protected $table = 'solicitudes';

    protected $fillable = [
        'organizacion_id', 'codigo', 'ticket_id', 'titulo', 'descripcion', 'solicitante_id', 'aprobador_id',
        'prioridad', 'estado', 'aprobada_en', 'completada_en',
    ];

    protected function casts(): array
    {
        return [
            'prioridad' => Prioridad::class,
            'estado' => EstadoSolicitud::class,
            'aprobada_en' => 'datetime',
            'completada_en' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'solicitante_id');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'aprobador_id');
    }
}
