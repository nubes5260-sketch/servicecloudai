<?php

namespace App\Models;

use App\Enums\Rol as RolCodigo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Rol extends Model
{
    public $timestamps = false;

    protected $table = 'roles';

    protected $fillable = ['codigo', 'nombre', 'descripcion', 'es_global'];

    protected function casts(): array
    {
        return ['es_global' => 'boolean'];
    }

    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'roles_permisos', 'rol_id', 'permiso_id');
    }

    public static function idDe(RolCodigo $codigo): int
    {
        return (int) static::where('codigo', $codigo->value)->value('id');
    }
}
