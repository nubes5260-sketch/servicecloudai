<?php

namespace App\Services;

use App\Enums\EstadoTicket;
use App\Enums\Impacto;
use App\Enums\Prioridad;
use App\Enums\TipoNotificacion;
use App\Enums\Urgencia;
use App\Models\Activo;
use App\Models\CategoriaTicket;
use App\Models\Servicio;
use App\Models\Ticket;
use App\Models\TicketAdjunto;
use App\Models\TicketAsignacion;
use App\Models\TicketComentario;
use App\Models\TicketHistorial;
use App\Models\TicketSla;
use App\Models\Usuario;
use App\Support\Codigos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Toda modificación de un ticket pasa por aquí: registra historial, asignaciones,
 * SLA y notificaciones (regla 02-laravel-postgres).
 */
class TicketService
{
    public function __construct(
        private SlaService $sla,
        private NotificacionService $notificaciones,
    ) {}

    // ------------------------------------------------------------------ crear

    public function crear(array $datos, Usuario $autor): Ticket
    {
        $ticket = DB::transaction(function () use ($datos, $autor) {
            $impacto = Impacto::from($datos['impacto'] ?? 'medio');
            $urgencia = Urgencia::from($datos['urgencia'] ?? 'media');
            $tecnicoId = $datos['tecnico_id'] ?? null;

            $ticket = new Ticket([
                'organizacion_id' => $datos['organizacion_id'],
                'codigo' => $this->siguienteCodigo(),
                'titulo' => $datos['titulo'],
                'descripcion' => $datos['descripcion'],
                'tipo' => $datos['tipo'] ?? 'incidente',
                'categoria_id' => $datos['categoria_id'] ?? null,
                'impacto' => $impacto,
                'urgencia' => $urgencia,
                'prioridad' => isset($datos['prioridad']) ? Prioridad::from($datos['prioridad']) : Prioridad::desdeMatriz($impacto, $urgencia),
                'estado' => $tecnicoId ? EstadoTicket::Asignado : EstadoTicket::Abierto,
                'origen' => $datos['origen'] ?? 'portal',
                'solicitante_id' => $datos['solicitante_id'] ?? $autor->id,
                'tecnico_id' => $tecnicoId,
                'equipo_id' => $datos['equipo_id'] ?? null,
                'servicio_id' => $datos['servicio_id'] ?? null,
                'activo_id' => $datos['activo_id'] ?? null,
            ]);
            $ticket->creado_en = now();
            $this->sla->aplicar($ticket);
            $ticket->save();
            $this->sla->sincronizarRegistro($ticket);

            $this->historial($ticket, $autor, 'creacion', null, 'Ticket creado desde '.$ticket->origen->label());
            if ($tecnicoId) {
                $this->registrarAsignacion($ticket, null, $tecnicoId, $autor, 'Asignación al registrar el ticket');
            }

            return $ticket;
        });

        if ($ticket->tecnico_id) {
            $this->notificaciones->notificar($ticket->tecnico_id, 'Ticket asignado', "Se te asignó {$ticket->codigo}: {$ticket->titulo}", TipoNotificacion::Asignacion, $ticket);
        } else {
            $this->asignarAutomaticamente($ticket, $autor);
        }
        if ($ticket->solicitante_id !== $autor->id) {
            $this->notificaciones->notificar($ticket->solicitante_id, 'Registramos tu ticket', "{$autor->nombre} registró {$ticket->codigo} a tu nombre: {$ticket->titulo}", TipoNotificacion::Ticket, $ticket);
        }

        return $ticket->refresh();
    }

    /**
     * TKT-AAAA-00001, correlativo por año (único en toda la plataforma).
     * Debe llamarse dentro de la transacción del alta: el bloqueo asesor evita que dos
     * peticiones simultáneas obtengan el mismo número (detectado en la prueba de carga).
     */
    public function siguienteCodigo(): string
    {
        Codigos::bloquear('tickets');
        $prefijo = 'TKT-'.now()->format('Y').'-';
        $maximo = Ticket::sinFiltroOrganizacion()->where('codigo', 'like', $prefijo.'%')
            ->selectRaw('max(substring(codigo from ?::int)::int) as maximo', [strlen($prefijo) + 1])
            ->value('maximo');

        return $prefijo.str_pad((string) ((int) $maximo + 1), 5, '0', STR_PAD_LEFT);
    }

    // ------------------------------------------------------------------ editar

    /** Actualiza datos descriptivos; prioridad y técnico pasan por sus métodos. */
    public function actualizar(Ticket $ticket, array $datos, Usuario $usuario): Ticket
    {
        return DB::transaction(function () use ($ticket, $datos, $usuario) {
            $prioridad = isset($datos['prioridad']) ? Prioridad::from($datos['prioridad']) : null;
            $tecnicoCambia = array_key_exists('tecnico_id', $datos);
            $tecnicoId = $datos['tecnico_id'] ?? null;
            unset($datos['prioridad'], $datos['tecnico_id']);

            $ticket->fill($datos);
            foreach (['titulo', 'tipo', 'impacto', 'urgencia', 'servicio_id', 'activo_id', 'categoria_id'] as $campo) {
                if ($ticket->isDirty($campo)) {
                    $this->historial($ticket, $usuario, Str::before($campo, '_id'),
                        $this->valorTexto($campo, $ticket->getOriginal($campo)), $this->valorTexto($campo, $ticket->getAttribute($campo)));
                }
            }
            $ticket->save();

            if ($prioridad && $prioridad !== $ticket->prioridad) {
                $this->cambiarPrioridad($ticket, $prioridad, $usuario);
            }
            if ($tecnicoCambia && (int) $tecnicoId !== (int) $ticket->tecnico_id) {
                $this->asignar($ticket, $tecnicoId ? (int) $tecnicoId : null, $usuario);
            }

            return $ticket;
        });
    }

    public function cambiarPrioridad(Ticket $ticket, Prioridad $prioridad, Usuario $usuario, string $motivo = ''): void
    {
        $anterior = $ticket->prioridad;
        $ticket->prioridad = $prioridad;
        // Misma política, regla de la nueva prioridad
        $politica = $ticket->politicaSla() ?? $this->sla->politicaPara($ticket->organizacion_id, $ticket->servicio_id);
        $regla = $this->sla->reglaPara($politica, $prioridad);
        if ($regla) {
            $this->sla->recalcularVencimientos($ticket, $regla, $ticket->sla?->minutos_pausados ?? 0);
        }
        $ticket->save();
        if ($regla) {
            TicketSla::updateOrCreate(['ticket_id' => $ticket->id], ['regla_sla_id' => $regla->id, 'calculado_en' => now()]);
            $ticket->unsetRelation('sla');
        }
        $this->historial($ticket, $usuario, 'prioridad', $anterior->value, $prioridad->value.($motivo ? " ($motivo)" : ''));
    }

    // ------------------------------------------------------------------ asignación

    /**
     * Ticket nuevo sin técnico: se asigna al técnico con menor carga (si la organización lo tiene activo).
     * Avisa a supervisores y administradores cuando no hay técnicos o el equipo está al límite.
     */
    private function asignarAutomaticamente(Ticket $ticket, Usuario $autor): void
    {
        $cargas = app(CargaTecnicos::class);
        $config = $cargas->configuracion($ticket->organizacion_id);
        $elegido = $config['automatica'] ? $cargas->elegir($ticket->organizacion_id) : null;

        if ($elegido) {
            $this->asignar($ticket, $elegido['id'], null, "Asignación automática por menor carga ({$elegido['activos']} tickets activos)");
        }

        [$titulo, $mensaje] = match (true) {
            ! $elegido && $config['automatica'] => ['Nuevo ticket sin asignar', "No hay técnicos activos para {$ticket->codigo}: {$ticket->titulo}"],
            ! $elegido => ['Nuevo ticket sin asignar', "{$ticket->codigo}: {$ticket->titulo}"],
            $elegido['activos'] >= $config['maximo'] => ['Equipo al límite de carga', "{$ticket->codigo} se asignó a {$elegido['nombre']}, que ya tenía {$elegido['activos']} tickets activos. Considera reasignar."],
            default => [null, null],
        };
        if ($titulo) {
            foreach ($this->notificaciones->gestoresDe($ticket->organizacion_id) as $gestor) {
                if ($gestor->id !== $autor->id) {
                    $this->notificaciones->notificar($gestor, $titulo, $mensaje, TipoNotificacion::Asignacion, $ticket);
                }
            }
        }
    }

    /** @param  Usuario|null  $usuario  null = asignación automática del sistema */
    public function asignar(Ticket $ticket, ?int $tecnicoId, ?Usuario $usuario, string $motivo = 'Asignación manual'): void
    {
        $anterior = $ticket->tecnico_id;
        if ((int) $anterior === (int) $tecnicoId) {
            return;
        }

        DB::transaction(function () use ($ticket, $tecnicoId, $usuario, $motivo, $anterior) {
            $ticket->tecnico_id = $tecnicoId;
            $estadoAnterior = $ticket->estado;
            if ($tecnicoId && $ticket->estado === EstadoTicket::Abierto) {
                $ticket->estado = EstadoTicket::Asignado;
            } elseif (! $tecnicoId && $ticket->estado === EstadoTicket::Asignado) {
                $ticket->estado = EstadoTicket::Abierto;
            }
            $ticket->save();
            $this->registrarAsignacion($ticket, $anterior, $tecnicoId, $usuario, $motivo);
            if ($estadoAnterior !== $ticket->estado) {
                $this->historial($ticket, $usuario, 'estado', $estadoAnterior->value, $ticket->estado->value);
            }
        });

        $nombre = $tecnicoId ? Usuario::sinFiltroOrganizacion()->whereKey($tecnicoId)->value('nombre') : null;
        if ($tecnicoId && $tecnicoId !== $usuario?->id) {
            $mensaje = $anterior ? "Se te reasignó {$ticket->codigo}: {$ticket->titulo}" : "Se te asignó {$ticket->codigo}: {$ticket->titulo}";
            $this->notificaciones->notificar($tecnicoId, $anterior ? 'Ticket reasignado a ti' : 'Ticket asignado', $mensaje, TipoNotificacion::Asignacion, $ticket);
        }
        if ($anterior && $tecnicoId && $anterior !== $usuario?->id) {
            $this->notificaciones->notificar($anterior, 'Ticket reasignado', "{$ticket->codigo} ahora lo atiende {$nombre}.", TipoNotificacion::Asignacion, $ticket);
        }
        if ($tecnicoId && $ticket->solicitante_id !== $usuario?->id) {
            [$titulo, $mensaje] = $anterior
                ? ['Cambió el técnico de tu ticket', "{$nombre} continuará con {$ticket->codigo}."]
                : ['Tu ticket ya tiene técnico', "{$nombre} revisará {$ticket->codigo}."];
            $this->notificaciones->notificar($ticket->solicitante_id, $titulo, $mensaje, TipoNotificacion::Ticket, $ticket);
        }
    }

    private function registrarAsignacion(Ticket $ticket, ?int $anterior, ?int $nuevo, ?Usuario $usuario, string $motivo): void
    {
        TicketAsignacion::create([
            'ticket_id' => $ticket->id, 'tecnico_anterior_id' => $anterior, 'tecnico_nuevo_id' => $nuevo,
            'equipo_id' => $ticket->equipo_id, 'asignado_por_id' => $usuario?->id, 'motivo' => $motivo, 'fecha' => now(),
        ]);
        $nombre = $nuevo ? Usuario::sinFiltroOrganizacion()->whereKey($nuevo)->value('nombre') : 'Sin asignar';
        $this->historial($ticket, $usuario, 'tecnico', $anterior ? Usuario::sinFiltroOrganizacion()->whereKey($anterior)->value('nombre') : null, $nombre);
    }

    // ------------------------------------------------------------------ estado

    /**
     * @param  string|null  $comentario  mensaje público para el solicitante (p. ej. qué información falta)
     * @param  bool  $notificar  false cuando quien llama ya envía su propio aviso
     */
    public function cambiarEstado(Ticket $ticket, EstadoTicket $nuevo, Usuario $usuario, ?string $solucion = null, ?string $comentario = null, bool $notificar = true): void
    {
        $anterior = $ticket->estado;
        if ($anterior === $nuevo) {
            return;
        }
        if (! $anterior->puedePasarA($nuevo)) {
            throw ValidationException::withMessages(['estado' => "No se puede pasar de \"{$anterior->label()}\" a \"{$nuevo->label()}\"."]);
        }
        if ($nuevo === EstadoTicket::Resuelto && blank($solucion ?? $ticket->solucion)) {
            throw ValidationException::withMessages(['solucion' => 'Describe la solución para marcar el ticket como resuelto.']);
        }
        if (in_array($nuevo, [EstadoTicket::Asignado, EstadoTicket::EnProgreso], true) && ! $ticket->tecnico_id) {
            throw ValidationException::withMessages(['estado' => 'Asigna un técnico antes de pasar a "'.$nuevo->label().'".']);
        }

        DB::transaction(function () use ($ticket, $anterior, $nuevo, $usuario, $solucion, $comentario) {
            $ahora = now();
            $ticket->estado = $nuevo;
            if (! $ticket->primera_respuesta_en && in_array($nuevo, [EstadoTicket::EnProgreso, EstadoTicket::EnEspera, EstadoTicket::Resuelto], true)) {
                $ticket->primera_respuesta_en = $ahora;
            }
            if ($nuevo === EstadoTicket::Resuelto) {
                $ticket->resuelto_en = $ahora;
                if (filled($solucion)) {
                    $ticket->solucion = $solucion;
                }
            }
            if ($anterior === EstadoTicket::Resuelto && $nuevo === EstadoTicket::EnProgreso) {
                $ticket->resuelto_en = null;
            }
            if ($nuevo === EstadoTicket::Cerrado) {
                $ticket->cerrado_en = $ahora;
            }
            $this->sla->alCambiarEstado($ticket, $anterior, $nuevo);
            $ticket->save();
            $this->historial($ticket, $usuario, 'estado', $anterior->value, $nuevo->value);
            if (filled($comentario)) {
                TicketComentario::create(['ticket_id' => $ticket->id, 'autor_id' => $usuario->id, 'contenido' => $comentario, 'es_interno' => false]);
            }
        });

        if (! $notificar) {
            return;
        }
        $extra = filled($comentario) ? ' «'.Str::limit($comentario, 120).'»' : '';
        if ($ticket->solicitante_id !== $usuario->id) {
            [$titulo, $mensaje] = $this->avisoSolicitante($ticket, $nuevo, $anterior);
            $this->notificaciones->notificar($ticket->solicitante_id, $titulo, $mensaje.$extra, TipoNotificacion::Ticket, $ticket);
        }
        if ($ticket->tecnico_id && $ticket->tecnico_id !== $usuario->id && $ticket->tecnico_id !== $ticket->solicitante_id) {
            $titulo = match (true) {
                $usuario->id === $ticket->solicitante_id && $nuevo === EstadoTicket::Cerrado => 'Solución confirmada',
                $usuario->id === $ticket->solicitante_id && $anterior === EstadoTicket::Resuelto => 'El usuario reabrió el ticket',
                default => 'Ticket actualizado',
            };
            $this->notificaciones->notificar($ticket->tecnico_id, $titulo, "{$usuario->nombre} pasó {$ticket->codigo} a {$nuevo->label()}.".$extra, TipoNotificacion::Ticket, $ticket);
        }
    }

    /** Aviso en lenguaje de usuario: qué significa el nuevo estado para quien reportó. */
    private function avisoSolicitante(Ticket $ticket, EstadoTicket $nuevo, EstadoTicket $anterior): array
    {
        $tecnico = $ticket->tecnico()->withoutGlobalScopes()->value('nombre') ?? 'El técnico';

        return match ($nuevo) {
            EstadoTicket::EnProgreso => $anterior === EstadoTicket::Resuelto
                ? ['Tu ticket fue reabierto', "{$ticket->codigo} volvió a atención."]
                : ['Están atendiendo tu ticket', "{$tecnico} está trabajando en {$ticket->codigo}."],
            EstadoTicket::EnEspera => ['Necesitamos tu respuesta', "{$tecnico} necesita información para continuar con {$ticket->codigo}."],
            EstadoTicket::Resuelto => ['Tu ticket fue resuelto', "Revisa la solución de {$ticket->codigo} y confírmala."],
            EstadoTicket::Cerrado => ['Tu ticket fue cerrado', "{$ticket->codigo} quedó cerrado."],
            EstadoTicket::Cancelado => ['Tu ticket fue cancelado', "{$ticket->codigo} fue cancelado."],
            EstadoTicket::Asignado => ['Tu ticket ya tiene técnico', "{$tecnico} revisará {$ticket->codigo}."],
            EstadoTicket::Abierto => ['Tu ticket cambió de estado', "{$ticket->codigo} volvió a la cola de asignación."],
        };
    }

    // ------------------------------------------------------------------ comentarios y adjuntos

    public function comentar(Ticket $ticket, Usuario $autor, string $contenido, bool $interno): TicketComentario
    {
        $comentario = TicketComentario::create(['ticket_id' => $ticket->id, 'autor_id' => $autor->id, 'contenido' => $contenido, 'es_interno' => $interno]);

        // La primera respuesta del técnico cuenta para el SLA de respuesta
        if (! $ticket->primera_respuesta_en && ! $interno && $autor->id === $ticket->tecnico_id) {
            $ticket->primera_respuesta_en = now();
            $ticket->save();
            $ticket->sla()->whereNull('respuesta_cumplida')->update(['respuesta_cumplida' => $ticket->primera_respuesta_en <= $ticket->vencimiento_respuesta]);
        }

        // El usuario respondió lo que se le pidió: el ticket sale de "En espera" y el SLA se reanuda
        $reanudado = false;
        if (! $interno && $autor->id === $ticket->solicitante_id && $ticket->estado === EstadoTicket::EnEspera && $ticket->tecnico_id) {
            $this->cambiarEstado($ticket, EstadoTicket::EnProgreso, $autor, notificar: false);
            $reanudado = true;
        }

        if (! $interno) {
            $esSolicitante = $autor->id === $ticket->solicitante_id;
            $destinatario = $esSolicitante ? $ticket->tecnico_id : $ticket->solicitante_id;
            $titulo = match (true) {
                $reanudado => 'El usuario respondió',
                $esSolicitante => 'Nuevo mensaje del usuario',
                default => 'Nuevo mensaje en tu ticket',
            };
            $mensaje = "{$autor->nombre} en {$ticket->codigo}: «".Str::limit($contenido, 120).'»'.($reanudado ? ' El ticket volvió a En progreso.' : '');
            $this->notificaciones->notificar($destinatario, $titulo, $mensaje, TipoNotificacion::Ticket, $ticket);
        }

        return $comentario;
    }

    public function adjuntar(Ticket $ticket, UploadedFile $archivo, Usuario $usuario): TicketAdjunto
    {
        $nombre = Str::uuid().'.'.($archivo->guessExtension() ?: $archivo->getClientOriginalExtension());
        $ruta = $archivo->storeAs("tickets/{$ticket->id}", $nombre, config('servicecloud.adjuntos_disco'));

        return TicketAdjunto::create([
            'ticket_id' => $ticket->id, 'subido_por_id' => $usuario->id,
            'nombre_original' => Str::limit($archivo->getClientOriginalName(), 250, ''),
            'ruta_storage' => $ruta, 'mime_type' => $archivo->getMimeType(), 'tamano_bytes' => $archivo->getSize(),
        ]);
    }

    // ------------------------------------------------------------------ apoyo

    public function historial(Ticket $ticket, ?Usuario $usuario, string $campo, ?string $anterior, ?string $nuevo): void
    {
        TicketHistorial::create([
            'ticket_id' => $ticket->id, 'usuario_id' => $usuario?->id, 'campo' => $campo,
            'valor_anterior' => $anterior, 'valor_nuevo' => $nuevo, 'fecha' => now(),
        ]);
    }

    private function nombreCategoria(?int $categoriaId): ?string
    {
        return $categoriaId ? CategoriaTicket::sinFiltroOrganizacion()->whereKey($categoriaId)->value('nombre') : null;
    }

    /** Valor legible para el historial (nombres en lugar de ids). */
    private function valorTexto(string $campo, mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        if ($valor instanceof \BackedEnum) {
            return method_exists($valor, 'label') ? $valor->label() : (string) $valor->value;
        }

        return match ($campo) {
            'servicio_id' => Servicio::sinFiltroOrganizacion()->whereKey($valor)->value('nombre'),
            'activo_id' => Activo::sinFiltroOrganizacion()->whereKey($valor)->value('nombre'),
            'categoria_id' => $this->nombreCategoria((int) $valor),
            default => (string) $valor,
        };
    }
}
