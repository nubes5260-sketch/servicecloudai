<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanSaas extends Model
{
    const CREATED_AT = 'creado_en';

    const UPDATED_AT = null;

    protected $table = 'planes_saas';

    protected $fillable = [
        'nombre', 'precio_mensual', 'limite_usuarios', 'limite_activos', 'limite_tickets', 'caracteristicas', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'precio_mensual' => 'decimal:2',
            'caracteristicas' => 'array',
            'activo' => 'boolean',
        ];
    }

    public function organizaciones(): HasMany
    {
        return $this->hasMany(Organizacion::class, 'plan_id');
    }
}
