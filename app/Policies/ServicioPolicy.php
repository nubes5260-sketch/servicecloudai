<?php

namespace App\Policies;

class ServicioPolicy extends PoliticaMaestro
{
    protected function modulo(): string
    {
        return 'servicios';
    }
}
