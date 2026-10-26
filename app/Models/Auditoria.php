<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAOrganizacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** fecha la pone la BD (DEFAULT CURRENT_TIMESTAMP). */
class Auditoria extends Model
{
    use PerteneceAOrganizacion;

    public $timestamps = false;

    protected $table = 'auditoria';

    protected $fillable = [
        'organizacion_id', 'usuario_id', 'entidad', 'entidad_id', 'accion', 'datos_antes', 'datos_despues', 'ip_origen',
    ];

    protected function casts(): array
    {
        return [
            'datos_antes' => 'array',
            'datos_despues' => 'array',
            'fecha' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
