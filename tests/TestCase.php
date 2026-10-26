<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase migra una sola vez por proceso: se siembran siempre
     * los datos demo para que todos los tests compartan la misma base.
     */
    protected $seed = true;
}
