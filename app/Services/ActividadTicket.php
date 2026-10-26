<?php

namespace App\Services;

use App\Enums\EstadoTicket;
use App\Enums\Prioridad;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Support\Formato;
use Illuminate\Support\Collection;

/** Línea de tiempo del detalle: historial + comentarios + adjuntos, del más reciente al más antiguo. */
class ActividadTicket
{
    /** @return Collection<int, array{icono: string, titulo: string, texto: string, fecha: \DateTimeInterface}> */
    public function para(Ticket $ticket, Usuario $usuario, bool $conComentarios = true): Collection
    {
        $ticket->loadMissing(['historial.usuario:id,nombre', 'comentarios.autor:id,nombre', 'adjuntos.subidoPor:id,nombre']);
        $verInternos = $usuario->can('verInternos', $ticket);
        $eventos = collect();

        foreach ($ticket->historial as $h) {
            $eventos->push([
                'icono' => match ($h->campo) {
                    'creacion' => 'ticket-check', 'estado' => 'refresh-cw', 'tecnico' => 'user-check', 'prioridad' => 'flag', default => 'pencil',
                },
                'titulo' => $h->usuario?->nombre ?? 'ServiceCloud · automático',
                'texto' => $this->describir($h->campo, $h->valor_anterior, $h->valor_nuevo).' · '.Formato::fechaHora($h->fecha),
                'fecha' => $h->fecha,
            ]);
        }

        foreach ($conComentarios ? $ticket->comentarios : [] as $c) {
            if ($c->es_interno && ! $verInternos) {
                continue; // nunca se muestran notas internas al solicitante
            }
            $eventos->push([
                'icono' => $c->es_interno ? 'lock' : 'message-square',
                'titulo' => $c->autor->nombre.($c->es_interno ? ' · nota interna' : ''),
                'texto' => $c->contenido.' · '.Formato::fechaHora($c->creado_en),
                'fecha' => $c->creado_en,
            ]);
        }

        foreach ($ticket->adjuntos as $a) {
            $eventos->push([
                'icono' => 'paperclip',
                'titulo' => $a->subidoPor?->nombre ?? 'Sistema',
                'texto' => 'Adjuntó '.$a->nombre_original.' · '.Formato::fechaHora($a->creado_en),
                'fecha' => $a->creado_en,
            ]);
        }

        return $eventos->sortByDesc(fn ($e) => $e['fecha']->getTimestamp())->values();
    }

    private function describir(string $campo, ?string $anterior, ?string $nuevo): string
    {
        return match ($campo) {
            'creacion' => $nuevo ?? 'Ticket creado.',
            'estado' => 'Cambió el estado de '.EstadoTicket::etiquetaDe($anterior).' a '.EstadoTicket::etiquetaDe($nuevo).'.',
            'tecnico' => $nuevo === 'Sin asignar' ? 'Liberó la asignación'.($anterior ? " de {$anterior}" : '').'.' : 'Asignó el ticket a '.$nuevo.'.',
            'prioridad' => 'Cambió la prioridad de '.Prioridad::etiquetaDe($anterior).' a '.Prioridad::etiquetaDe(strtok((string) $nuevo, ' ')).'.',
            default => 'Actualizó '.str_replace('_', ' ', $campo).($nuevo !== null ? ': '.$nuevo : '').'.',
        };
    }
}
