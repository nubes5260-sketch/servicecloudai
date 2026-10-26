<?php

namespace App\Models;

use App\Enums\EstadoIncidencia;
use App\Enums\Impacto;
use App\Enums\Prioridad;
use App\Enums\Urgencia;
use App\Models\Concerns\PerteneceAOrganizacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Sin creado_en: usa inicio_en (DEFAULT CURRENT_TIMESTAMP). */
class Incidencia extends Model
{
    use PerteneceAOrganizacion;

    public $timestamps = false;

    protected $table = 'incidencias';

    protected $fillable = [
        'organizacion_id', 'codigo', 'ticket_id', 'problema_id', 'servicio_id', 'descripcion', 'impacto',
        'urgencia', 'prioridad', 'responsable_id', 'estado', 'inicio_en', 'resuelta_en',
    ];

    protected function casts(): array
    {
        return [
            'impacto' => Impacto::class,
            'urgencia' => Urgencia::class,
            'prioridad' => Prioridad::class,
            'estado' => EstadoIncidencia::class,
            'inicio_en' => 'datetime',
            'resuelta_en' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function problema(): BelongsTo
    {
        return $this->belongsTo(Problema::class, 'problema_id');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsable_id');
    }
}
