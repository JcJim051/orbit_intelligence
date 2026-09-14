<?php

namespace Tests\Feature;

use App\Enums\DatasetFormVersionStatus;
use App\Enums\UserRole;
use App\Models\SpatialDataset;
use App\Models\User;
use Database\Seeders\RiskDatasetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiskDatasetSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_versioned_risk_form_as_an_editable_draft(): void
    {
        $this->seed(RiskDatasetSeeder::class);

        $dataset = SpatialDataset::query()->where('slug', 'puntos-criticos')->firstOrFail();
        $version = $dataset->versions()->with('fields')->firstOrFail();

        $this->assertSame(DatasetFormVersionStatus::Draft, $version->status);
        $this->assertCount(9, $version->fields);
        $this->assertSame(
            ['Bajo', 'Medio', 'Alto', 'Crítico'],
            $version->fields->firstWhere('key', 'nivel_riesgo')->options,
        );
        $this->assertTrue($version->fields->firstWhere('key', 'poblacion_afectada_inicial')->available_for_analytics);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)
            ->get(route('admin.spatial-datasets.index'))
            ->assertOk()
            ->assertSee('Puntos críticos')
            ->assertSee('Tratamiento histórico')
            ->assertSee('Población afectada inicial');
    }
}
