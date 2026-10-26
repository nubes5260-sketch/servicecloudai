<?php

namespace App\Policies;

class ProblemaPolicy extends IncidenciaPolicy
{
    protected function modulo(): string
    {
        return 'problemas';
    }
}
