<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAOrganizacion;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionOrganizacion extends Model
{
    use PerteneceAOrganizacion;

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'organizacion_id';

    protected $table = 'configuracion_organizacion';

    protected $fillable = [
        'organizacion_id', 'zona_horaria', 'configuracion',
    ];

    protected function casts(): array
    {
        return [
            'configuracion' => 'array',
        ];
    }
}
