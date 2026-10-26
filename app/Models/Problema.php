<?php

namespace App\Models;

use App\Enums\EstadoProblema;
use App\Models\Concerns\PerteneceAOrganizacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Problema extends Model
{
    use PerteneceAOrganizacion;

    const CREATED_AT = 'creado_en';

    const UPDATED_AT = null;

    protected $table = 'problemas';

    protected $fillable = [
        'organizacion_id', 'codigo', 'titulo', 'descripcion', 'causa_raiz', 'solucion_temporal',
        'solucion_definitiva', 'servicio_id', 'responsable_id', 'estado', 'cerrado_en',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoProblema::class,
            'cerrado_en' => 'datetime',
        ];
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsable_id');
    }

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'problemas_tickets', 'problema_id', 'ticket_id');
    }

    public function incidencias(): HasMany
    {
        return $this->hasMany(Incidencia::class, 'problema_id');
    }
}
