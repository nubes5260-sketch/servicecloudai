<?php

namespace App\Models;

use App\Enums\EstadoServicio;
use App\Models\Concerns\PerteneceAOrganizacion;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Servicio extends Model
{
    use PerteneceAOrganizacion;

    const CREATED_AT = 'creado_en';

    const UPDATED_AT = null;

    protected $table = 'servicios';

    protected $fillable = [
        'organizacion_id', 'codigo', 'nombre', 'descripcion', 'categoria_id', 'responsable_id',
        'contrato_id', 'politica_sla_id', 'disponibilidad_objetivo', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoServicio::class,
            'disponibilidad_objetivo' => 'decimal:2',
        ];
    }

    /** Categoría del catálogo categorias_servicio de la organización. */
    public function categoriaCatalogo(): BelongsTo
    {
        return $this->belongsTo(CategoriaServicio::class, 'categoria_id');
    }

    /** Nombre de la categoría (solo lectura; se guarda categoria_id). */
    protected function categoria(): Attribute
    {
        return Attribute::get(fn () => $this->categoriaCatalogo?->nombre);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsable_id');
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }

    public function politicaSla(): BelongsTo
    {
        return $this->belongsTo(PoliticaSla::class, 'politica_sla_id');
    }

    /** Activos que soportan el servicio (tabla N:M servicios_activos). */
    public function activos(): BelongsToMany
    {
        return $this->belongsToMany(Activo::class, 'servicios_activos', 'servicio_id', 'activo_id')
            ->withPivot('organizacion_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'servicio_id');
    }

    public function incidencias(): HasMany
    {
        return $this->hasMany(Incidencia::class, 'servicio_id');
    }
}
