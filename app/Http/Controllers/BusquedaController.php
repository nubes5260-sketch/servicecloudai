<?php

namespace App\Http\Controllers;

use App\Models\Activo;
use App\Models\Ticket;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/** Búsqueda global del header: tickets, usuarios y activos (máx. 8), respetando tenant y rol. */
class BusquedaController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['resultados' => []]);
        }
        /** @var Usuario $usuario */
        $usuario = $request->user();
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
        $resultados = [];

        $tickets = Ticket::visiblesPara($usuario)
            ->where(fn (Builder $c) => $c->where('codigo', 'ilike', $like)->orWhere('titulo', 'ilike', $like))
            ->latest('creado_en')->limit(4)->get(['id', 'codigo', 'titulo']);
        foreach ($tickets as $ticket) {
            $resultados[] = ['tipo' => 'Ticket', 'titulo' => $ticket->codigo, 'sub' => $ticket->titulo, 'icono' => 'ticket-check', 'url' => $this->urlTicket($ticket)];
        }

        if ($usuario->puedeVerModulo('usuarios')) {
            $usuarios = Usuario::where(fn (Builder $c) => $c->where('nombre', 'ilike', $like)->orWhere('email', 'ilike', $like))
                ->orderBy('nombre')->limit(3)->get(['id', 'nombre', 'email']);
            foreach ($usuarios as $u) {
                $resultados[] = ['tipo' => 'Usuario', 'titulo' => $u->nombre, 'sub' => $u->email, 'icono' => 'user', 'url' => route('usuarios.index', ['q' => $u->email])];
            }
        }

        if ($usuario->puedeVerModulo('activos')) {
            $activos = Activo::with('organizacion:id,razon_social')
                ->where(fn (Builder $c) => $c->where('nombre', 'ilike', $like)->orWhere('codigo', 'ilike', $like)->orWhere('ip', 'ilike', $like))
                ->orderBy('nombre')->limit(3)->get();
            foreach ($activos as $a) {
                $resultados[] = ['tipo' => 'Activo', 'titulo' => $a->nombre, 'sub' => $a->tipo->label().' · '.$a->organizacion?->razon_social, 'icono' => 'monitor', 'url' => route('activos.index', ['q' => $a->nombre])];
            }
        }

        return response()->json(['resultados' => array_slice($resultados, 0, 8)]);
    }

    private function urlTicket(Ticket $ticket): string
    {
        return Route::has('tickets.show')
            ? route('tickets.show', $ticket)
            : route('tickets.index', ['q' => $ticket->codigo]);
    }
}
