<?php

namespace App\Http\Controllers;

use App\Services\ReporteService;
use App\Support\Csv;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteController extends Controller
{
    public function __construct(private ReporteService $reportes) {}

    public function index(Request $request): View
    {
        [$desde, $hasta] = $this->periodo($request);

        return view('reportes.index', [
            'reportes' => ReporteService::catalogo(),
            'resumen' => $this->reportes->resumen($request->user(), $desde, $hasta),
            'desde' => $desde, 'hasta' => $hasta,
        ]);
    }

    public function descargar(Request $request): StreamedResponse
    {
        $request->validate(['reporte' => ['required', Rule::in(ReporteService::catalogo()->keys()->all())]]);
        [$desde, $hasta] = $this->periodo($request);
        $reporte = (string) $request->string('reporte');
        $datos = $this->reportes->generar($reporte, $request->user(), $desde, $hasta);

        return Csv::descargar($reporte, $datos['encabezados'], $datos['filas']);
    }

    /** Período del filtro (por defecto, los últimos 30 días; máximo un año). */
    private function periodo(Request $request): array
    {
        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);
        $hasta = $request->filled('hasta') ? CarbonImmutable::parse($request->input('hasta'))->endOfDay() : CarbonImmutable::now()->endOfDay();
        $desde = $request->filled('desde') ? CarbonImmutable::parse($request->input('desde'))->startOfDay() : $hasta->subDays(29)->startOfDay();
        if ($desde->diffInDays($hasta) > 366) {
            $desde = $hasta->subYear()->startOfDay();
        }

        return [$desde, $hasta];
    }
}
