<?php

namespace Tests\Feature\GestionBancos;

use Tests\TestCase;

class PrototipoTest extends TestCase
{
    public function test_public_prototype_displays_the_submission_tracking_and_internal_screens(): void
    {
        $this->get(route('gestion-bancos.prototipo'))
            ->assertOk()
            ->assertViewIs('gestion-bancos.prototipo')
            ->assertSee('Vista de demostración')
            ->assertSee('Radicar documentos')
            ->assertSee('Consultar trámite')
            ->assertSee('Bandeja de gestión');
    }
}
