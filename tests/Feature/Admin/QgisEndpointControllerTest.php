<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Postgis\ManagedPostgisConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QgisEndpointControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_updates_the_qgis_endpoint_without_replacing_database_credentials(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $configuration = $this->mock(ManagedPostgisConfiguration::class);
        $configuration->expects('updateQgisEndpoint')->once()->with('192.168.1.204', 5432);

        $this->actingAs($admin)->patch(route('admin.postgis.qgis-endpoint.update'), [
            'qgis_host' => '192.168.1.204',
            'qgis_port' => 5432,
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'qgis_endpoint_updated',
            'actor_id' => $admin->id,
        ]);
        $audit = AuditLog::query()->where('event', 'qgis_endpoint_updated')->firstOrFail();
        $this->assertSame('192.168.1.204', $audit->metadata['host']);
    }

    public function test_qgis_endpoint_rejects_an_invalid_address_and_port(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->patch(route('admin.postgis.qgis-endpoint.update'), [
            'qgis_host' => 'http://invalid host',
            'qgis_port' => 70000,
        ])->assertInvalid(['qgis_host', 'qgis_port']);
    }
}
