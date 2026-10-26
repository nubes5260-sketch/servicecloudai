<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** API JSON de tickets: mismas reglas de tenant, rol y validación que la interfaz web. */
class TicketApiController extends Controller
{
    public function __construct(private TicketService $tickets) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Ticket::class);
        $porPagina = min(100, max(1, $request->integer('por_pagina', 15)));

        $tickets = Ticket::visiblesPara($request->user())
            ->with(['organizacion:id,razon_social', 'tecnico:id,nombre', 'servicio:id,nombre', 'categoriaTicket:id,nombre', 'sla.regla'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->when($request->filled('prioridad'), fn ($q) => $q->where('prioridad', $request->string('prioridad')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('codigo', 'ilike', '%'.$request->string('q').'%')
                ->orWhere('titulo', 'ilike', '%'.$request->string('q').'%')))
            ->orderByDesc('creado_en')
            ->paginate($porPagina)->withQueryString();

        return TicketResource::collection($tickets);
    }

    public function show(Ticket $ticket): TicketResource
    {
        $this->authorize('view', $ticket);

        return new TicketResource($ticket->load(['organizacion', 'tecnico', 'servicio', 'categoriaTicket', 'sla.regla']));
    }

    public function store(GuardarTicketRequest $request): JsonResponse
    {
        $datos = $request->datosConOrganizacion();
        unset($datos['adjunto']);
        $datos['origen'] ??= 'api';
        $ticket = $this->tickets->crear($datos, $request->user());

        return (new TicketResource($ticket->load(['organizacion', 'tecnico', 'servicio', 'categoriaTicket', 'sla.regla'])))
            ->response()->setStatusCode(201);
    }
}
