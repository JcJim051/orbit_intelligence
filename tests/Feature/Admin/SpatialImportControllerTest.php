<?php

namespace Tests\Feature\Admin;

use App\Enums\SpatialImportStatus;
use App\Enums\UserRole;
use App\Models\SpatialImport;
use App\Models\User;
use App\Services\Postgis\ManagedPostgisConfiguration;
use App\Services\Postgis\ProvisionSpatialImportStaging;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SpatialImportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_cannot_access_spatial_imports(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($member)->get(route('admin.spatial-imports.index'))->assertForbidden();
    }

    public function test_admin_creates_isolated_staging_access_and_receives_credentials(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->mock(ProvisionSpatialImportStaging::class, function (MockInterface $mock): void {
            $mock->shouldReceive('provision')->once()->withArgs(fn (SpatialImport $import): bool => str_starts_with($import->staging_schema, 'staging_'));
        });
        $this->mock(ManagedPostgisConfiguration::class, function (MockInterface $mock): void {
            $mock->shouldReceive('summary')->once()->andReturn([
                'host' => '127.0.0.1', 'port' => 55432, 'database' => 'siid_meta', 'sslmode' => 'disable',
                'qgis_host' => '192.168.1.204', 'qgis_port' => 5432,
            ]);
        });

        $this->actingAs($admin)->post(route('admin.spatial-imports.store'), [
            'name' => 'Límites departamentales',
            'sector' => 'Planeación',
            'purpose' => 'Primera carga oficial.',
            'valid_for_hours' => 72,
            'status' => 'contract_draft',
        ])->assertRedirect()->assertSessionHas('status')->assertSessionHas('spatial_import_credentials');

        $import = SpatialImport::query()->firstOrFail();
        $this->assertSame(SpatialImportStatus::StagingReady, $import->status);
        $this->assertSame($admin->id, $import->created_by);
        $this->assertStringStartsWith('staging_', $import->staging_schema);
        $this->assertStringStartsWith('stg_', $import->database_username);
        $this->assertDatabaseHas('audit_logs', ['event' => 'spatial_import_staging_created', 'actor_id' => $admin->id]);
    }

    public function test_admin_sees_escaped_import_content(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        SpatialImport::factory()->create(['name' => '<script>alert("x")</script>']);
        $this->mock(ManagedPostgisConfiguration::class, fn (MockInterface $mock) => $mock->shouldReceive('summary')->once()->andReturn(['configured' => true]));

        $this->actingAs($admin)
            ->get(route('admin.spatial-imports.index'))
            ->assertOk()
            ->assertDontSee('<script>alert("x")</script>', false);
    }

    public function test_admin_sees_safe_qgis_9377_import_instructions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->mock(ManagedPostgisConfiguration::class, fn (MockInterface $mock) => $mock->shouldReceive('summary')->once()->andReturn(['configured' => true]));

        $this->actingAs($admin)
            ->get(route('admin.spatial-imports.index'))
            ->assertOk()
            ->assertSee('Cómo cargar capas EPSG:9377 desde QGIS')
            ->assertSee('Exportar a PostgreSQL (conexiones existentes)')
            ->assertSee('Reproyectar a este SRC en la salida')
            ->assertSee('Desmarque «Sobrescribir tabla existente»');
    }

    public function test_approved_import_offers_qgis_access_renewal_without_reanalysis(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        SpatialImport::factory()->create([
            'status' => SpatialImportStatus::Approved,
            'expires_at' => now()->subDay(),
        ]);
        $this->mock(ManagedPostgisConfiguration::class, fn (MockInterface $mock) => $mock->shouldReceive('summary')->once()->andReturn(['configured' => true]));

        $this->actingAs($admin)
            ->get(route('admin.spatial-imports.index'))
            ->assertOk()
            ->assertDontSee('Analizar nuevamente el esquema')
            ->assertSee('Reactivar acceso QGIS')
            ->assertSee('Conserva el mismo usuario, contraseña y esquema');
    }
}
