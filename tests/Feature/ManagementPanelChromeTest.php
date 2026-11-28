<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementPanelChromeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_gestion_uses_the_institutional_frame(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get('/gestion')
            ->assertOk()
            ->assertSee('Ir al portal GOV.CO', false)
            ->assertSee('Departamento Administrativo de Planeación', false)
            ->assertSee('logo_blanco.png', false);
    }

    public function test_login_uses_the_institutional_frame(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Ir al portal GOV.CO', false)
            ->assertSee('Departamento Administrativo de Planeación', false)
            ->assertSee('Gobernación del Meta', false);
    }
}
