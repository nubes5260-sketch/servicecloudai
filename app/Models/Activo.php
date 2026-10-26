<?php

namespace App\Models;

use App\Enums\EstadoActivo;
use App\Enums\TipoActivo;
use App\Models\Concerns\PerteneceAOrganizacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activo extends Model
{
    use PerteneceAOrganizacion;

    const CREATED_AT = 'creado_en';

    const UPDATED_AT = null;

    protected $table = 'activos';

    protected $fillable = [
        'organizacion_id', 'codigo', 'nombre', 'tipo', 'numero_serie', 'fabricante', 'modelo', 'ip', 'hostname',
        'ubicacion', 'responsable_id', 'proveedor_cloud', 'region_cloud', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoActivo::class,
            'estado' => EstadoActivo::class,
        ];
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsable_id');
    }

    /** Servicios soportados (fuente principal: servicios_activos). */
    public function servicios(): BelongsToMany
    {
        return $this->belongsToMany(Servicio::class, 'servicios_activos', 'activo_id', 'servicio_id')
            ->withPivot('organizacion_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'activo_id');
    }
}
