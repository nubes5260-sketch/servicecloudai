<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Etiqueta extends Model
{
    public $timestamps = false;

    protected $table = 'etiquetas';

    protected $fillable = ['nombre'];

    public function articulos(): BelongsToMany
    {
        return $this->belongsToMany(ArticuloConocimiento::class, 'articulos_etiquetas', 'etiqueta_id', 'articulo_id');
    }
}
