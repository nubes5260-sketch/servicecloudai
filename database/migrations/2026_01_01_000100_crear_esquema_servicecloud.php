<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Carga el esquema oficial de ServiceCloud AI (45 tablas) tal cual.
 * El SQL es la fuente de verdad: no se reescribe con Schema Builder.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/servicecloud_ai_schema.sql')));
    }

    public function down(): void
    {
        // El esquema no es reversible por partes.
        // Para reconstruir la base usa: php artisan migrate:fresh --seed
    }
};
