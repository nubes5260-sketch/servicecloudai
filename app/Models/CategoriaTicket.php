<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAOrganizacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaTicket extends Model
{
    use PerteneceAOrganizacion;

    public $timestamps = false;

    protected $table = 'categorias_tickets';

    protected $fillable = ['organizacion_id', 'nombre', 'padre_id'];

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'padre_id');
    }

    public function hijas(): HasMany
    {
        return $this->hasMany(self::class, 'padre_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'categoria_id');
    }
}
