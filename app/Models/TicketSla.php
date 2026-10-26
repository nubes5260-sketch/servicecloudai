<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketSla extends Model
{
    public $timestamps = false;

    protected $table = 'tickets_sla';

    protected $fillable = [
        'ticket_id', 'regla_sla_id', 'respuesta_cumplida', 'resolucion_cumplida', 'minutos_pausados', 'ultima_pausa_en', 'calculado_en',
    ];

    protected function casts(): array
    {
        return [
            'respuesta_cumplida' => 'boolean',
            'resolucion_cumplida' => 'boolean',
            'minutos_pausados' => 'integer',
            'ultima_pausa_en' => 'datetime',
            'calculado_en' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function regla(): BelongsTo
    {
        return $this->belongsTo(ReglaSla::class, 'regla_sla_id');
    }
}
