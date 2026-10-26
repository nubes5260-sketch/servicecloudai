<?php

namespace App\Policies;

class ContratoPolicy extends PoliticaMaestro
{
    protected function modulo(): string
    {
        return 'contratos';
    }
}
