<?php

namespace App\Models;

use App\Enums\EstadoContrato;
use App\Models\Concerns\PerteneceAOrganizacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contrato extends Model
{
    use PerteneceAOrganizacion;

    const CREATED_AT = 'creado_en';

    const UPDATED_AT = null;

    protected $table = 'contratos';

    protected $fillable = [
        'organizacion_id', 'codigo', 'nombre', 'fecha_inicio', 'fecha_fin', 'monto', 'moneda', 'estado', 'politica_sla_id', 'descripcion',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoContrato::class,
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'monto' => 'decimal:2',
        ];
    }

    public function politicaSla(): BelongsTo
    {
        return $this->belongsTo(PoliticaSla::class, 'politica_sla_id');
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(Servicio::class, 'contrato_id');
    }
}
