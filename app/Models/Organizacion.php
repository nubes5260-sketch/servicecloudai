<?php

namespace App\Models;

use App\Enums\EstadoOrganizacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Tenant. No usa el trait de organización: el acceso se controla en su Policy. */
class Organizacion extends Model
{
    const CREATED_AT = 'creado_en';

    const UPDATED_AT = 'actualizado_en';

    protected $table = 'organizaciones';

    protected $fillable = [
        'codigo', 'razon_social', 'ruc', 'sector', 'email', 'telefono', 'direccion', 'plan_id', 'estado',
    ];

    protected function casts(): array
    {
        return ['estado' => EstadoOrganizacion::class];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanSaas::class, 'plan_id');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'organizacion_id');
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(Servicio::class, 'organizacion_id');
    }

    public function activos(): HasMany
    {
        return $this->hasMany(Activo::class, 'organizacion_id');
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class, 'organizacion_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'organizacion_id');
    }

    public function politicasSla(): HasMany
    {
        return $this->hasMany(PoliticaSla::class, 'organizacion_id');
    }

    public function equipos(): HasMany
    {
        return $this->hasMany(EquipoSoporte::class, 'organizacion_id');
    }

    public function categoriasTickets(): HasMany
    {
        return $this->hasMany(CategoriaTicket::class, 'organizacion_id');
    }

    public function configuracion(): HasOne
    {
        return $this->hasOne(ConfiguracionOrganizacion::class, 'organizacion_id');
    }
}
