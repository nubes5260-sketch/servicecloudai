<?php

namespace App\Services;

use App\Enums\EstadoTicket;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Support\Formato;

/**
 * Traduce el estado de un ticket a lenguaje de usuario:
 * - pasos del progreso (Registrado → Asignado → En atención → Resuelto → Cerrado),
 * - mensaje para el solicitante ("¿qué está pasando con mi ticket?"),
 * - siguiente paso sugerido para el técnico o la gestión.
 */
class FlujoTicket
{
    public function __construct(private SlaService $sla) {}

    /** @return list<array{titulo: string, detalle: string, estado: string}> estado: hecho|actual|pausa|pendiente|cancelado */
    public function pasos(Ticket $ticket): array
    {
        $e = $ticket->estado;
        $orden = match ($e) {
            EstadoTicket::Abierto => 0,
            EstadoTicket::Asignado => 1,
            EstadoTicket::EnProgreso, EstadoTicket::EnEspera => 2,
            EstadoTicket::Resuelto => 3,
            EstadoTicket::Cerrado => 5, // todo completo
            EstadoTicket::Cancelado => -1,
        };
        $pasos = [
            ['Registrado', Formato::fechaHora($ticket->creado_en)],
            ['Asignado', $ticket->tecnico?->nombre ?? 'Buscando técnico'],
            [$e === EstadoTicket::EnEspera ? 'En espera' : 'En atención', $ticket->primera_respuesta_en ? Formato::fechaHora($ticket->primera_respuesta_en) : 'Pendiente'],
            ['Resuelto', $ticket->resuelto_en ? Formato::fechaHora($ticket->resuelto_en) : 'Pendiente'],
            ['Cerrado', $ticket->cerrado_en ? Formato::fechaHora($ticket->cerrado_en) : 'Pendiente'],
        ];

        return collect($pasos)->map(function ($paso, $i) use ($orden, $e) {
            $estado = match (true) {
                $orden === -1 => $i === 0 ? 'hecho' : 'cancelado',
                $i < $orden => 'hecho',
                $i === $orden => $e === EstadoTicket::EnEspera ? 'pausa' : 'actual',
                default => 'pendiente',
            };

            // Un paso pendiente nunca muestra fecha (aunque el dato exista por un flujo anterior)
            $detalle = in_array($estado, ['pendiente', 'cancelado'], true) && $i !== 1 ? 'Pendiente' : $paso[1];

            return ['titulo' => $paso[0], 'detalle' => $estado === 'cancelado' ? '—' : $detalle, 'estado' => $estado];
        })->all();
    }

    /**
     * Mensaje principal para quien reportó el ticket.
     *
     * @return array{icono: string, tono: string, titulo: string, texto: string}
     */
    public function mensajeSolicitante(Ticket $ticket): array
    {
        $tecnico = $ticket->tecnico?->nombre;
        $plazo = $ticket->vencimiento_resolucion ? Formato::fechaHora($ticket->vencimiento_resolucion) : null;
        $vencido = $ticket->vencimiento_resolucion && $ticket->vencimiento_resolucion->isPast();
        $textoPlazo = $plazo ? ($vencido
            ? ' El plazo estimado ya pasó y tu caso sigue en atención prioritaria.'
            : " Plazo estimado de solución: {$plazo}.") : '';

        return match ($ticket->estado) {
            EstadoTicket::Abierto => ['icono' => 'inbox', 'tono' => 'info', 'titulo' => 'Recibimos tu ticket',
                'texto' => 'Estamos asignando al técnico adecuado. Te avisaremos en cuanto alguien lo tome.'.$textoPlazo],
            EstadoTicket::Asignado => ['icono' => 'user-check', 'tono' => 'info', 'titulo' => "{$tecnico} revisará tu caso",
                'texto' => 'Tu ticket ya tiene técnico asignado y está en su cola de trabajo.'.$textoPlazo],
            EstadoTicket::EnProgreso => ['icono' => 'wrench', 'tono' => 'activo', 'titulo' => "{$tecnico} está atendiendo tu ticket",
                'texto' => 'El técnico está trabajando en la solución. Puedes escribirle abajo si tienes información nueva.'.$textoPlazo],
            EstadoTicket::EnEspera => ['icono' => 'message-circle-question', 'tono' => 'aviso', 'titulo' => 'Necesitamos tu respuesta',
                'texto' => ($tecnico ?? 'El técnico').' necesita información para continuar. Responde en la conversación y el ticket seguirá su curso automáticamente.'],
            EstadoTicket::Resuelto => ['icono' => 'circle-check-big', 'tono' => 'exito', 'titulo' => 'Tu ticket fue resuelto',
                'texto' => 'Revisa la solución. Si todo funciona, confírmalo; si el problema sigue, indícalo y lo reabrimos.'],
            EstadoTicket::Cerrado => ['icono' => 'badge-check', 'tono' => 'exito', 'titulo' => 'Ticket cerrado',
                'texto' => 'El caso quedó resuelto y cerrado. Si vuelve a ocurrir, crea un nuevo ticket.'],
            EstadoTicket::Cancelado => ['icono' => 'circle-slash', 'tono' => 'neutro', 'titulo' => 'Ticket cancelado',
                'texto' => 'Este ticket fue cancelado y ya no se está atendiendo.'],
        };
    }

    /** Texto corto de la situación para listados ("Esperando tu respuesta"). */
    public function situacionCorta(Ticket $ticket): array
    {
        return match ($ticket->estado) {
            EstadoTicket::Abierto => ['Buscando técnico', 'blue'],
            EstadoTicket::Asignado => ['Técnico asignado', 'blue'],
            EstadoTicket::EnProgreso => ['En atención', 'purple'],
            EstadoTicket::EnEspera => ['Esperando tu respuesta', 'yellow'],
            EstadoTicket::Resuelto => ['Confirma la solución', 'green'],
            EstadoTicket::Cerrado => ['Cerrado', 'gray'],
            EstadoTicket::Cancelado => ['Cancelado', 'gray'],
        };
    }

    /**
     * Siguiente paso para quien atiende (técnico o gestión).
     *
     * @return array{titulo: string, texto: string, acciones: list<string>}
     *                                                                      acciones: asignar|iniciar|pedir_info|reanudar|resolver|cerrar|reabrir|cancelar
     */
    public function siguientePaso(Ticket $ticket, Usuario $usuario): array
    {
        $puedeAsignar = $usuario->can('asignar', Ticket::class);
        $estadoSla = $this->sla->estadoSla($ticket);
        $urgencia = match ($estadoSla['tipo']) {
            'vencido' => ' El SLA ya venció: priorízalo.',
            'riesgo' => " Quedan {$estadoSla['texto']} de SLA.",
            'normal' => " Quedan {$estadoSla['texto']} de SLA.",
            default => '',
        };

        $paso = match ($ticket->estado) {
            EstadoTicket::Abierto => $ticket->tecnico_id
                ? ['Inicia la atención', 'El ticket tiene técnico pero aún no se empezó a trabajar.', ['iniciar', 'cancelar']]
                : ['Asigna un técnico', 'Nadie atiende este ticket todavía (no había técnicos disponibles o la asignación automática está desactivada).'.($puedeAsignar ? ' Elige a quién asignarlo según su carga.' : ''), $puedeAsignar ? ['asignar', 'cancelar'] : []],
            EstadoTicket::Asignado => ['Inicia la atención', 'Al iniciar, el usuario recibe un aviso de que su ticket está siendo atendido y se registra la primera respuesta del SLA.', ['iniciar', 'pedir_info', 'cancelar']],
            EstadoTicket::EnProgreso => ['Resuelve o pide información', 'Cuando lo soluciones, describe la solución para que el usuario la confirme. Si necesitas datos del usuario, pídeselos: el SLA se pausa mientras espera.', ['resolver', 'pedir_info', 'cancelar']],
            EstadoTicket::EnEspera => ['Esperando al usuario', 'El SLA está en pausa. Cuando el usuario responda, el ticket vuelve a "En progreso" automáticamente; también puedes reanudarlo tú.', ['reanudar', 'resolver', 'cancelar']],
            EstadoTicket::Resuelto => ['Esperando confirmación', 'El usuario debe confirmar la solución. Puedes cerrarlo directamente o reabrirlo si hace falta.', ['cerrar', 'reabrir']],
            EstadoTicket::Cerrado => ['Ticket cerrado', 'No quedan acciones pendientes.', []],
            EstadoTicket::Cancelado => ['Ticket cancelado', 'No quedan acciones pendientes.', []],
        };

        // Reasignar (gestión) o pedir reasignación (técnico a cargo), antes de "cancelar"
        $acciones = $paso[2];
        $extra = match (true) {
            ! $ticket->tecnico_id || ! $ticket->estado->esActivo() => null,
            $puedeAsignar => 'reasignar',
            $usuario->can('solicitarReasignacion', $ticket) => 'solicitar_reasignacion',
            default => null,
        };
        if ($extra) {
            $posicion = array_search('cancelar', $acciones, true);
            array_splice($acciones, $posicion === false ? count($acciones) : $posicion, 0, [$extra]);
        }

        return ['titulo' => $paso[0], 'texto' => $paso[1].($ticket->estado->esActivo() ? $urgencia : ''), 'acciones' => $acciones];
    }
}
