<?php

namespace App\Policies;

class ActivoPolicy extends PoliticaMaestro
{
    protected function modulo(): string
    {
        return 'activos';
    }
}
