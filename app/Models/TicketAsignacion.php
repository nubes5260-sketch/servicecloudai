<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAsignacion extends Model
{
    public $timestamps = false;

    protected $table = 'tickets_asignaciones';

    protected $fillable = ['ticket_id', 'tecnico_anterior_id', 'tecnico_nuevo_id', 'equipo_id', 'asignado_por_id', 'motivo', 'fecha'];

    protected function casts(): array
    {
        return ['fecha' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function tecnicoAnterior(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'tecnico_anterior_id');
    }

    public function tecnicoNuevo(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'tecnico_nuevo_id');
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(EquipoSoporte::class, 'equipo_id');
    }

    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'asignado_por_id');
    }
}
