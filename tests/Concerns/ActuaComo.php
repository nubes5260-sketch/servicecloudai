<?php

namespace Tests\Concerns;

use App\Models\Usuario;

trait ActuaComo
{
    protected function usuario(string $email): Usuario
    {
        return Usuario::sinFiltroOrganizacion()->where('email', $email)->firstOrFail();
    }

    protected function como(string $email): static
    {
        return $this->actingAs($this->usuario($email));
    }

    /** Petición AJAX como la que envía el modal (form[data-ajax]). */
    protected function ajax(): static
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);
    }
}
