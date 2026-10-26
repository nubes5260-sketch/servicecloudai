<?php

namespace App\Models;

use App\Enums\HorarioServicio;
use App\Enums\Prioridad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReglaSla extends Model
{
    public $timestamps = false;

    protected $table = 'reglas_sla';

    protected $fillable = ['politica_sla_id', 'prioridad', 'respuesta_minutos', 'resolucion_minutos', 'horario_servicio'];

    protected function casts(): array
    {
        return [
            'prioridad' => Prioridad::class,
            'horario_servicio' => HorarioServicio::class,
            'respuesta_minutos' => 'integer',
            'resolucion_minutos' => 'integer',
        ];
    }

    public function politica(): BelongsTo
    {
        return $this->belongsTo(PoliticaSla::class, 'politica_sla_id');
    }
}
