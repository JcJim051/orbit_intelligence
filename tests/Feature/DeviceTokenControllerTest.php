<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceTokenControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_a_qgis_catalog_token(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('tokens.store'), [
            'name' => 'QGIS Riesgos',
            'purpose' => 'qgis',
        ])->assertRedirect()->assertSessionHas('new_token');

        $this->assertSame(['qgis:read'], $admin->tokens()->firstOrFail()->abilities);
    }

    public function test_non_administrator_cannot_create_a_qgis_catalog_token(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($member)->post(route('tokens.store'), [
            'name' => 'QGIS no autorizado',
            'purpose' => 'qgis',
        ])->assertForbidden();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
