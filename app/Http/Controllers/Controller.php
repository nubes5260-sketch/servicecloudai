<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Respuesta tras guardar: JSON {redirect} para los formularios AJAX del modal,
     * o redirección normal. El mensaje llega como toast (session 'ok').
     *
     * @param  string|array{0: string, 1?: string}  $mensaje
     */
    protected function guardado(string $url, string|array $mensaje, string $tipo = 'ok'): JsonResponse|RedirectResponse
    {
        session()->flash($tipo, $mensaje);

        return request()->expectsJson()
            ? response()->json(['redirect' => $url])
            : redirect()->to($url);
    }

    /**
     * Opciones para selects de relaciones. El filtro de organización ya lo pone el scope;
     * para el Admin SaaS editando un registro se limitan a la organización de ese registro.
     */
    protected function opcionesRelacion(Builder $consulta, ?Model $editado = null): Collection
    {
        if ($editado?->exists && $editado->organizacion_id) {
            $consulta->where($consulta->getModel()->qualifyColumn('organizacion_id'), $editado->organizacion_id);
        }

        return $consulta->get();
    }

    /**
     * Formularios y detalles que se abren en el modal: parcial para fetch,
     * página completa si se abre la URL directamente.
     */
    protected function vistaModal(string $vista, array $datos = []): View
    {
        if (request()->ajax()) {
            return view($vista, $datos);
        }

        return view('partials.pagina-modal', ['vista' => $vista, 'datos' => $datos]);
    }
}
