<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketHistorial extends Model
{
    public $timestamps = false;

    protected $table = 'tickets_historial';

    protected $fillable = ['ticket_id', 'usuario_id', 'campo', 'valor_anterior', 'valor_nuevo', 'fecha'];

    protected function casts(): array
    {
        return ['fecha' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
