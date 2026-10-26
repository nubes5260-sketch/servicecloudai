<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAdjunto extends Model
{
    const CREATED_AT = 'creado_en';

    const UPDATED_AT = null;

    protected $table = 'tickets_adjuntos';

    protected $fillable = ['ticket_id', 'subido_por_id', 'nombre_original', 'ruta_storage', 'mime_type', 'tamano_bytes'];

    protected function casts(): array
    {
        return ['tamano_bytes' => 'integer'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'subido_por_id');
    }
}
