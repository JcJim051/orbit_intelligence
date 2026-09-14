<?php

namespace Tests\Feature\Api;

use App\Enums\DatasetFormVersionStatus;
use App\Enums\DatasetStatus;
use App\Enums\UserRole;
use App\Models\DatasetFormField;
use App\Models\DatasetFormVersion;
use App\Models\SpatialDataset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QgisDatasetControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_qgis_token_can_list_only_active_datasets_with_a_published_form(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $published = SpatialDataset::factory()->create(['status' => DatasetStatus::Active, 'slug' => 'puntos-criticos']);
        DatasetFormVersion::factory()->create([
            'spatial_dataset_id' => $published->id,
            'status' => DatasetFormVersionStatus::Published,
            'published_at' => now(),
        ]);
        SpatialDataset::factory()->create(['status' => DatasetStatus::Draft, 'slug' => 'oculto']);
        $token = $user->createToken('QGIS pruebas', ['qgis:read'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/qgis/datasets');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'puntos-criticos')
            ->assertJsonPath('data.0.materialized', false);
    }

    public function test_manifest_exposes_the_published_version_field_rules_and_coverage(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $dataset = SpatialDataset::factory()->create([
            'status' => DatasetStatus::Active,
            'slug' => 'puntos-criticos',
            'geometry_type' => 'point',
        ]);
        $version = DatasetFormVersion::factory()->create([
            'spatial_dataset_id' => $dataset->id,
            'version' => 2,
            'status' => DatasetFormVersionStatus::Published,
            'effective_from' => '2026-09-12',
            'published_at' => now(),
        ]);
        DatasetFormField::factory()->create([
            'dataset_form_version_id' => $version->id,
            'key' => 'poblacion_afectada',
            'label' => 'Población afectada',
            'section' => 'Afectaciones',
            'field_type' => 'integer',
            'required' => true,
            'validation_rules' => ['min' => 0],
            'historical_policy' => 'optional_backfill',
            'introduced_in_version' => 2,
            'public_visible' => true,
        ]);
        DatasetFormField::factory()->create([
            'dataset_form_version_id' => $version->id,
            'key' => 'interno',
            'visible_in_qgis' => false,
        ]);
        $token = $user->createToken('QGIS pruebas', ['qgis:read'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/qgis/datasets/puntos-criticos/form');

        $response->assertOk()
            ->assertJsonPath('data.dataset.srid', 4326)
            ->assertJsonPath('data.form.version', 2)
            ->assertJsonPath('data.fields.0.key', 'poblacion_afectada')
            ->assertJsonPath('data.fields.0.validation.min', 0)
            ->assertJsonPath('data.fields.0.historical_policy', 'optional_backfill')
            ->assertJsonPath('data.fields.0.introduced_in_version', 2)
            ->assertJsonCount(1, 'data.fields')
            ->assertJsonPath('data.sections.0', 'Afectaciones')
            ->assertJsonStructure(['data' => ['coverage_notice']]);
    }

    public function test_a_token_without_qgis_ability_cannot_read_the_catalog(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('iPhone', ['meetings:upload'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/qgis/datasets')->assertForbidden();
    }
}
