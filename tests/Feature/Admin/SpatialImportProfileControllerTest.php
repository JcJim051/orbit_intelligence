<?php

namespace Tests\Feature\Admin;

use App\Enums\SpatialImportStatus;
use App\Enums\UserRole;
use App\Models\SpatialImport;
use App\Models\User;
use App\Services\Postgis\SpatialImportProfiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SpatialImportProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_profiles_tables_loaded_from_qgis(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $import = SpatialImport::factory()->create();
        $profile = ['tables' => [[
            'name' => 'limites_meta',
            'row_count' => 29,
            'columns' => [['name' => 'municipio', 'data_type' => 'text', 'udt_name' => 'text', 'nullable' => true]],
            'geometries' => [['column' => 'geom', 'type' => 'MULTIPOLYGON', 'srid' => 4326]],
        ]], 'profiled_at' => now()->toIso8601String()];
        $this->mock(SpatialImportProfiler::class, fn (MockInterface $mock) => $mock->shouldReceive('profile')->once()->withArgs(fn (SpatialImport $bound): bool => $bound->is($import))->andReturn($profile));

        $this->actingAs($admin)->post(route('admin.spatial-imports.profile.store', $import), ['confirm' => '1'])
            ->assertRedirect()->assertSessionHas('status');

        $this->assertSame(SpatialImportStatus::Profiled, $import->fresh()->status);
        $this->assertSame('limites_meta', $import->fresh()->profile['tables'][0]['name']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'spatial_import_profiled', 'actor_id' => $admin->id]);
    }

    public function test_approved_import_detects_additional_tables_without_losing_approval(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $import = SpatialImport::factory()->create([
            'status' => SpatialImportStatus::Approved,
            'selected_table' => 'capa_inicial',
            'profile' => ['tables' => [['name' => 'capa_inicial']]],
        ]);
        $profile = ['tables' => [
            ['name' => 'capa_inicial', 'row_count' => 2, 'columns' => [], 'geometries' => []],
            ['name' => 'capa_nueva', 'row_count' => 3, 'columns' => [], 'geometries' => []],
        ], 'profiled_at' => now()->toIso8601String()];
        $this->mock(SpatialImportProfiler::class, fn (MockInterface $mock) => $mock->shouldReceive('profile')->once()->andReturn($profile));

        $this->actingAs($admin)->post(route('admin.spatial-imports.profile.store', $import), ['confirm' => '1'])
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $message): bool => str_contains($message, '1 nueva(s)'));

        $this->assertSame(SpatialImportStatus::Approved, $import->fresh()->status);
        $this->assertSame('capa_inicial', $import->fresh()->selected_table);
        $this->assertSame('capa_nueva', $import->fresh()->profile['tables'][1]['name']);
    }

    public function test_approved_import_shows_refresh_action_and_additional_table(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        SpatialImport::factory()->create([
            'name' => 'Fabian_SIID',
            'status' => SpatialImportStatus::Approved,
            'selected_table' => 'capa_inicial',
            'profile' => ['tables' => [
                ['name' => 'capa_inicial', 'row_count' => 1, 'columns' => [], 'geometries' => []],
                ['name' => 'capa_nueva', 'row_count' => 2, 'columns' => [], 'geometries' => []],
            ]],
        ]);

        $this->actingAs($admin)->get(route('admin.spatial-imports.index'))
            ->assertOk()
            ->assertSee('Fabian_SIID')
            ->assertSee('Actualizar capas cargadas')
            ->assertSee('capa_nueva');
    }

    public function test_closed_import_cannot_be_profiled_again(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $import = SpatialImport::factory()->create(['status' => SpatialImportStatus::Closed]);

        $this->actingAs($admin)->post(route('admin.spatial-imports.profile.store', $import), ['confirm' => '1'])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(SpatialImportStatus::Closed, $import->fresh()->status);
    }
}
