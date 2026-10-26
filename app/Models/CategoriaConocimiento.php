<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Catálogo global de categorías de la base de conocimiento (con el ícono de la interfaz). */
class CategoriaConocimiento extends Model
{
    public $timestamps = false;

    protected $table = 'categorias_conocimiento';

    protected $fillable = ['nombre', 'icono'];

    public function articulos(): HasMany
    {
        return $this->hasMany(ArticuloConocimiento::class, 'categoria_id');
    }

    public static function idDe(string $nombre): ?int
    {
        return static::query()->where('nombre', $nombre)->value('id');
    }
}
