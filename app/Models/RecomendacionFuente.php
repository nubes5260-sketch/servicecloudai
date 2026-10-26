<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class RecomendacionFuente extends Pivot
{
    public $timestamps = false;

    protected $table = 'recomendaciones_fuentes';

    protected function casts(): array
    {
        return ['relevancia' => 'float'];
    }
}
