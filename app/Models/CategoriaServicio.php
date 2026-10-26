<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAOrganizacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Catálogo de categorías de servicio por organización (antes servicios.categoria en texto libre). */
class CategoriaServicio extends Model
{
    use PerteneceAOrganizacion;

    public $timestamps = false;

    protected $table = 'categorias_servicio';

    protected $fillable = ['organizacion_id', 'nombre'];

    public function servicios(): HasMany
    {
        return $this->hasMany(Servicio::class, 'categoria_id');
    }

    /** Id de la categoría con ese nombre en la organización (la crea si no existe). */
    public static function idPara(int $organizacionId, ?string $nombre): ?int
    {
        $nombre = trim((string) $nombre);

        return $nombre === '' ? null : static::sinFiltroOrganizacion()
            ->firstOrCreate(['organizacion_id' => $organizacionId, 'nombre' => $nombre])->id;
    }
}
