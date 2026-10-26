<?php

namespace App\Models;

use App\Enums\EstadoUsuario;
use App\Enums\Rol as RolCodigo;
use App\Models\Concerns\PerteneceAOrganizacion;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, Notifiable, PerteneceAOrganizacion;

    const CREATED_AT = 'creado_en';

    const UPDATED_AT = 'actualizado_en';

    protected $table = 'usuarios';

    protected $fillable = [
        'organizacion_id', 'nombre', 'email', 'password_hash', 'telefono', 'cargo', 'estado', 'ultimo_acceso',
    ];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoUsuario::class,
            'ultimo_acceso' => 'datetime',
        ];
    }

    // --- Autenticación: la contraseña está en password_hash y no hay remember_token ---

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    // --- Relaciones ---

    /**
     * Roles del usuario (tabla única usuarios_roles). Su ámbito es la organización del usuario:
     * ADMIN_SAAS (global) sin organización; el resto dentro de usuarios.organizacion_id (trigger en BD).
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'usuarios_roles', 'usuario_id', 'rol_id');
    }

    public function equipos(): BelongsToMany
    {
        return $this->belongsToMany(EquipoSoporte::class, 'equipos_miembros', 'usuario_id', 'equipo_id')
            ->withPivot('organizacion_id');
    }

    public function ticketsAsignados(): HasMany
    {
        return $this->hasMany(Ticket::class, 'tecnico_id');
    }

    public function ticketsSolicitados(): HasMany
    {
        return $this->hasMany(Ticket::class, 'solicitante_id');
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class, 'usuario_id');
    }

    public function preferencias(): HasOne
    {
        return $this->hasOne(PreferenciaNotificacion::class, 'usuario_id');
    }

    // --- Helpers de rol ---

    /** @return list<string> códigos de rol del usuario */
    public function codigosRol(): array
    {
        return $this->roles->pluck('codigo')->all();
    }

    public function tieneRol(string|RolCodigo ...$codigos): bool
    {
        $codigos = array_map(fn ($c) => $c instanceof RolCodigo ? $c->value : $c, $codigos);

        return count(array_intersect($codigos, $this->codigosRol())) > 0;
    }

    public function esAdminSaas(): bool
    {
        return $this->organizacion_id === null && $this->tieneRol(RolCodigo::AdminSaas);
    }

    /** Rol de mayor jerarquía, para mostrar en el header y en listados. */
    public function rolPrincipal(): ?RolCodigo
    {
        $codigos = $this->codigosRol();
        foreach (RolCodigo::cases() as $rol) {
            if (in_array($rol->value, $codigos, true)) {
                return $rol;
            }
        }

        return null;
    }

    /** Identificador legible para listados (la tabla no tiene código): USR-101... */
    public function codigoVisible(): string
    {
        return sprintf('USR-%03d', 100 + $this->id);
    }

    public function iniciales(): string
    {
        $partes = preg_split('/\s+/', trim($this->nombre)) ?: [];
        $iniciales = '';
        foreach (array_slice($partes, 0, 2) as $parte) {
            $iniciales .= Str::upper(Str::substr($parte, 0, 1));
        }

        return $iniciales ?: '--';
    }

    /** ¿El rol del usuario puede ver este módulo del menú? (config/servicecloud.php) */
    public function puedeVerModulo(string $clave): bool
    {
        $roles = config("servicecloud.menu.$clave.roles", []);

        return $this->tieneRol(...$roles);
    }

    public function estaActivo(): bool
    {
        return $this->estado === EstadoUsuario::Activo;
    }
}
