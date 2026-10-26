<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketComentario extends Model
{
    const CREATED_AT = 'creado_en';

    const UPDATED_AT = null;

    protected $table = 'tickets_comentarios';

    protected $fillable = ['ticket_id', 'autor_id', 'contenido', 'es_interno'];

    protected function casts(): array
    {
        return ['es_interno' => 'boolean'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'autor_id');
    }
}
