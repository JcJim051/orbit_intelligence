<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Postgis\ManagedPostgisConfiguration;
use App\Services\Postgis\PrepareManagedPostgis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostgisConnectionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_administrator_cannot_access_postgis_configuration(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($member)->get(route('admin.postgis.index'))->assertForbidden();
    }

    public function test_administrator_can_open_postgis_configuration_without_exposing_passwords(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $configuration = $this->mock(ManagedPostgisConfiguration::class);
        $configuration->expects('summary')->once()->andReturn([
            'configured' => true,
            'active' => false,
            'prepared_at' => null,
            'activated_at' => null,
            'host' => 'db.internal',
            'port' => 5432,
            'qgis_host' => 'gis.institutional.test',
            'qgis_port' => 5432,
            'database' => 'siid_meta',
            'sslmode' => 'require',
            'admin_username' => 'siid_owner',
            'app_username' => 'siid_app',
            'qgis_username' => 'qgis_editor',
            'reader_username' => 'geoserver_reader',
        ]);

        $this->actingAs($admin)->get(route('admin.postgis.index'))
            ->assertOk()
            ->assertSee('db.internal')
            ->assertDontSee('owner-password-very-long');
    }

    public function test_connection_payload_requires_distinct_users_and_long_passwords(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('admin.postgis.connection.store'), [
            ...$this->validPayload(),
            'qgis_username' => 'siid_app',
            'qgis_password' => 'short',
        ])->assertInvalid(['qgis_username', 'qgis_password']);
    }

    public function test_administrator_tests_and_stores_connection_without_a_plaintext_audit_secret(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $payload = $this->validPayload();
        $configuration = $this->mock(ManagedPostgisConfiguration::class);
        $configuration->expects('testAndStore')->once()->with($payload);

        $this->actingAs($admin)->post(route('admin.postgis.connection.store'), $payload)
            ->assertRedirect()
            ->assertSessionHas('status');

        $audit = AuditLog::query()->where('event', 'postgis_connection_configured')->firstOrFail();
        $this->assertSame('db.internal', $audit->metadata['host']);
        $this->assertArrayNotHasKey('admin_password', $audit->metadata);
    }

    public function test_administrator_can_start_preparation_from_the_frontend(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $preparation = $this->mock(PrepareManagedPostgis::class);
        $preparation->expects('handle')->once()->andReturn([]);

        $this->actingAs($admin)->post(route('admin.postgis.preparation.store'))
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'host' => 'db.internal',
            'port' => 5432,
            'qgis_host' => 'gis.institutional.test',
            'qgis_port' => 5432,
            'database' => 'siid_meta',
            'sslmode' => 'require',
            'admin_username' => 'siid_owner',
            'admin_password' => 'owner-password-very-long',
            'app_username' => 'siid_app',
            'app_password' => 'application-password-long',
            'qgis_username' => 'qgis_editor',
            'qgis_password' => 'qgis-password-very-long',
            'reader_username' => 'geoserver_reader',
            'reader_password' => 'reader-password-very-long',
        ];
    }
}
