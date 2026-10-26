<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAOrganizacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** organizacion_id NULL = plantilla global de plataforma. */
class PoliticaSla extends Model
{
    use PerteneceAOrganizacion;

    protected static bool $incluyeGlobales = true;

    const CREATED_AT = 'creado_en';

    const UPDATED_AT = null;

    protected $table = 'politicas_sla';

    protected $fillable = ['organizacion_id', 'nombre', 'descripcion', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function reglas(): HasMany
    {
        return $this->hasMany(ReglaSla::class, 'politica_sla_id');
    }

    public function esPlantilla(): bool
    {
        return $this->organizacion_id === null;
    }
}
