<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAOrganizacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipoSoporte extends Model
{
    use PerteneceAOrganizacion;

    public $timestamps = false;

    protected $table = 'equipos_soporte';

    protected $fillable = ['organizacion_id', 'nombre', 'descripcion', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function miembros(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'equipos_miembros', 'equipo_id', 'usuario_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'equipo_id');
    }
}
