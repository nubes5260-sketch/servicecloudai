<?php

namespace App\Models;

use App\Enums\EstadoTicket;
use App\Enums\Impacto;
use App\Enums\OrigenTicket;
use App\Enums\Prioridad;
use App\Enums\Rol;
use App\Enums\TipoTicket;
use App\Enums\Urgencia;
use App\Models\Concerns\PerteneceAOrganizacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ticket extends Model
{
    use PerteneceAOrganizacion;

    const CREATED_AT = 'creado_en';

    const UPDATED_AT = 'actualizado_en';

    protected $table = 'tickets';

    protected $fillable = [
        'organizacion_id', 'codigo', 'titulo', 'descripcion', 'tipo', 'categoria_id',
        'prioridad', 'impacto', 'urgencia', 'estado', 'origen', 'solicitante_id', 'tecnico_id', 'equipo_id',
        'servicio_id', 'activo_id', 'primera_respuesta_en', 'vencimiento_respuesta',
        'vencimiento_resolucion', 'resuelto_en', 'cerrado_en', 'solucion',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoTicket::class,
            'prioridad' => Prioridad::class,
            'impacto' => Impacto::class,
            'urgencia' => Urgencia::class,
            'estado' => EstadoTicket::class,
            'origen' => OrigenTicket::class,
            'primera_respuesta_en' => 'datetime',
            'vencimiento_respuesta' => 'datetime',
            'vencimiento_resolucion' => 'datetime',
            'resuelto_en' => 'datetime',
            'cerrado_en' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'codigo';
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'solicitante_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'tecnico_id');
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(EquipoSoporte::class, 'equipo_id');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class, 'activo_id');
    }

    /**
     * Política SLA vigente: se deriva de tickets_sla → reglas_sla → politicas_sla
     * (ya no se guarda en tickets para que no haya dos fuentes del mismo dato).
     */
    public function politicaSla(): ?PoliticaSla
    {
        $this->loadMissing(['sla.regla.politica' => fn ($q) => $q->withoutGlobalScopes()]);

        return $this->sla?->regla?->politica;
    }

    /** Regla elegida por SlaService::aplicar() antes de guardar (no es una columna). */
    public ?ReglaSla $reglaSlaPendiente = null;

    /** Categoría del catálogo categorias_tickets (la subcategoría es su padre_id). */
    public function categoriaTicket(): BelongsTo
    {
        return $this->belongsTo(CategoriaTicket::class, 'categoria_id');
    }

    public function comentarios(): HasMany
    {
        return $this->hasMany(TicketComentario::class, 'ticket_id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(TicketHistorial::class, 'ticket_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(TicketAsignacion::class, 'ticket_id');
    }

    public function adjuntos(): HasMany
    {
        return $this->hasMany(TicketAdjunto::class, 'ticket_id');
    }

    public function sla(): HasOne
    {
        return $this->hasOne(TicketSla::class, 'ticket_id');
    }

    public function problemas(): BelongsToMany
    {
        return $this->belongsToMany(Problema::class, 'problemas_tickets', 'ticket_id', 'problema_id');
    }

    public function incidencia(): HasOne
    {
        return $this->hasOne(Incidencia::class, 'ticket_id');
    }

    /**
     * Alcance por rol, además del filtro de organización:
     * técnico → asignados a él; solicitante → los que registró.
     */
    public function scopeVisiblesPara(Builder $consulta, Usuario $usuario): Builder
    {
        if ($usuario->esAdminSaas() || $usuario->tieneRol(Rol::AdminOrganizacion, Rol::Supervisor)) {
            return $consulta;
        }
        if ($usuario->tieneRol(Rol::Tecnico)) {
            return $consulta->where(fn (Builder $q) => $q->where('tecnico_id', $usuario->id)->orWhere('solicitante_id', $usuario->id));
        }

        return $consulta->where('solicitante_id', $usuario->id);
    }

    /** Tickets que aún consumen SLA (no resueltos, cerrados ni cancelados). */
    public function scopeAbiertos(Builder $consulta): Builder
    {
        return $consulta->whereNotIn('estado', [EstadoTicket::Resuelto, EstadoTicket::Cerrado, EstadoTicket::Cancelado]);
    }

    public function estaAbierto(): bool
    {
        return ! in_array($this->estado, [EstadoTicket::Resuelto, EstadoTicket::Cerrado, EstadoTicket::Cancelado], true);
    }

    /** Nombre de la categoría para mostrar. */
    public function nombreCategoria(): ?string
    {
        return $this->categoriaTicket?->nombre;
    }
}
