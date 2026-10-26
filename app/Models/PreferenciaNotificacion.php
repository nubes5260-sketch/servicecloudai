<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreferenciaNotificacion extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'usuario_id';

    protected $table = 'preferencias_notificaciones';

    protected $fillable = ['usuario_id', 'email_activo', 'push_activo', 'in_app_activo'];

    protected function casts(): array
    {
        return [
            'email_activo' => 'boolean',
            'push_activo' => 'boolean',
            'in_app_activo' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
