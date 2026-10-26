<?php

namespace App\Models;

use App\Enums\CanalNotificacion;
use App\Enums\TipoNotificacion;
use App\Models\Concerns\PerteneceAOrganizacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notificacion extends Model
{
    use PerteneceAOrganizacion;

    protected static bool $incluyeGlobales = true;

    const CREATED_AT = 'creado_en';

    const UPDATED_AT = null;

    protected $table = 'notificaciones';

    protected $fillable = [
        'organizacion_id', 'usuario_id', 'ticket_id', 'titulo', 'mensaje', 'tipo', 'canal', 'leida_en', 'enviada_en',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoNotificacion::class,
            'canal' => CanalNotificacion::class,
            'leida_en' => 'datetime',
            'enviada_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function scopeNoLeidas(Builder $consulta): Builder
    {
        return $consulta->whereNull('leida_en');
    }

    public function scopeDe(Builder $consulta, Usuario $usuario): Builder
    {
        return $consulta->where('usuario_id', $usuario->id);
    }

    /** Ícono y tono para la campana: [ícono lucide, tono]. tono: info|aviso|exito|peligro|neutro */
    public function apariencia(): array
    {
        $titulo = mb_strtolower($this->titulo);

        return match (true) {
            str_contains($titulo, 'respuesta') || str_contains($titulo, 'reabri') => ['message-circle-question', 'aviso'],
            str_contains($titulo, 'resuelto') || str_contains($titulo, 'confirmada') || str_contains($titulo, 'cerrado') => ['circle-check-big', 'exito'],
            str_contains($titulo, 'mensaje') || str_contains($titulo, 'respondió') || str_contains($titulo, 'comentario') => ['message-square', 'info'],
            str_contains($titulo, 'atendiendo') => ['wrench', 'info'],
            str_contains($titulo, 'técnico') || str_contains($titulo, 'asignado') => ['user-check', 'info'],
            str_contains($titulo, 'vencid') || str_contains($titulo, 'cancelado') => ['triangle-alert', 'peligro'],
            default => match ($this->tipo) {
                TipoNotificacion::Asignacion => ['user-check', 'info'],
                TipoNotificacion::Sla => ['timer', 'aviso'],
                TipoNotificacion::Sistema => ['settings', 'neutro'],
                default => ['ticket', 'info'],
            },
        };
    }
}
