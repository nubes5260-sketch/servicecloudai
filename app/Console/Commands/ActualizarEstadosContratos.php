<?php

namespace App\Console\Commands;

use App\Services\ContratoService;
use Illuminate\Console\Command;

class ActualizarEstadosContratos extends Command
{
    protected $signature = 'contratos:actualizar-estados';

    protected $description = 'Marca los contratos como por vencer (≤ 30 días) o vencidos según su fecha de fin';

    public function handle(ContratoService $contratos): int
    {
        $cambios = $contratos->actualizarEstados();
        $this->info("Contratos actualizados: {$cambios}");

        return self::SUCCESS;
    }
}
