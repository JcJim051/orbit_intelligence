<?php

namespace Tests\Feature\Admin;

use App\Enums\DatasetFieldType;
use App\Enums\DatasetFormVersionStatus;
use App\Enums\DatasetStatus;
use App\Enums\HistoricalDataPolicy;
use App\Enums\SpatialImportStatus;
use App\Enums\UserRole;
use App\Models\DatasetFormField;
use App\Models\DatasetFormVersion;
use App\Models\SpatialDataset;
use App\Models\SpatialImport;
use App\Models\SpatialImportContract;
use App\Models\User;
use App\Services\Postgis\MaterializeSpatialDataset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class DatasetFormVersionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_catalog_options_stored_as_json_text_are_normalized(): void
    {
        $field = DatasetFormField::factory()->create(['options' => ['Bajo', 'Alto']]);
        DB::table('dataset_form_fields')->where('id', $field->id)->update([
            'options' => json_encode(json_encode(['Bajo', 'Alto'])),
        ]);

        $this->assertSame(['Bajo', 'Alto'], $field->fresh()->options);
    }

    public function test_admin_configures_field_rules_and_historical_policy_on_draft(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $dataset = SpatialDataset::factory()->create();
        $version = DatasetFormVersion::factory()->for($dataset, 'dataset')->create();

        $this->actingAs($admin)->post(route('admin.spatial-datasets.versions.fields.store', [$dataset, $version]), [
            ...$this->fieldPayload(),
            'key' => 'nivel_riesgo',
            'label' => 'Nivel de riesgo',
            'field_type' => 'select',
            'options' => "Bajo\nMedio\nAlto\nCrítico",
            'historical_policy' => 'optional_backfill',
            'required' => 1,
            'public_visible' => 1,
            'min_value' => 0,
            'max_value' => 100,
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $field = DatasetFormField::query()->where('key', 'nivel_riesgo')->firstOrFail();
        $this->assertSame(DatasetFieldType::Select, $field->field_type);
        $this->assertSame(HistoricalDataPolicy::OptionalBackfill, $field->historical_policy);
        $this->assertSame(['Bajo', 'Medio', 'Alto', 'Crítico'], $field->options);
        $this->assertSame(['min' => 0, 'max' => 100, 'max_length' => 500], $field->validation_rules);
        $this->assertTrue($field->required);
        $this->assertTrue($field->public_visible);
    }

    public function test_reserved_system_key_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $dataset = SpatialDataset::factory()->create();
        $version = DatasetFormVersion::factory()->for($dataset, 'dataset')->create();

        $this->actingAs($admin)->post(route('admin.spatial-datasets.versions.fields.store', [$dataset, $version]), [
            ...$this->fieldPayload(),
            'key' => 'created_at',
        ])->assertRedirect()->assertInvalid('key');

        $this->assertDatabaseMissing('dataset_form_fields', ['dataset_form_version_id' => $version->id]);
    }

    public function test_publishing_requires_at_least_one_field(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $dataset = SpatialDataset::factory()->create();
        $version = DatasetFormVersion::factory()->for($dataset, 'dataset')->create();

        $this->actingAs($manager)->post(route('admin.spatial-datasets.versions.publication.store', [$dataset, $version]), [
            'effective_from' => '2026-09-12',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(DatasetFormVersionStatus::Draft, $version->fresh()->status);
    }

    public function test_administrator_can_approve_dataset_publication(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $dataset = SpatialDataset::factory()->create();
        $version = DatasetFormVersion::factory()->for($dataset, 'dataset')->create();
        DatasetFormField::factory()->for($version, 'formVersion')->create();

        $this->actingAs($admin)->post(route('admin.spatial-datasets.versions.publication.store', [$dataset, $version]), [
            'effective_from' => '2026-09-13',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(DatasetFormVersionStatus::Published, $version->fresh()->status);
        $this->assertSame($admin->id, $version->fresh()->approved_by);
    }

    public function test_publishing_on_postgresql_prepares_the_qgis_table_automatically(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $dataset = SpatialDataset::factory()->create();
        $version = DatasetFormVersion::factory()->for($dataset, 'dataset')->create();
        DatasetFormField::factory()->for($version, 'formVersion')->create();
        $materializer = $this->mock(MaterializeSpatialDataset::class);
        $materializer->shouldReceive('isAvailable')->once()->andReturnTrue();
        $materializer->shouldReceive('materialize')->once()->withArgs(fn (SpatialDataset $given): bool => $given->is($dataset));

        $this->actingAs($manager)->post(route('admin.spatial-datasets.versions.publication.store', [$dataset, $version]), [
            'effective_from' => '2026-09-13',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(DatasetFormVersionStatus::Published, $version->fresh()->status);
    }

    public function test_failed_automatic_preparation_is_visible_and_can_be_retried(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $dataset = SpatialDataset::factory()->create();
        $version = DatasetFormVersion::factory()->for($dataset, 'dataset')->create();
        DatasetFormField::factory()->for($version, 'formVersion')->create();
        $materializer = $this->mock(MaterializeSpatialDataset::class);
        $materializer->shouldReceive('isAvailable')->once()->andReturnTrue();
        $materializer->shouldReceive('materialize')->once()->andThrow(new RuntimeException('PostGIS no disponible'));

        $this->actingAs($manager)->post(route('admin.spatial-datasets.versions.publication.store', [$dataset, $version]), [
            'effective_from' => '2026-09-13',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(DatasetFormVersionStatus::Published, $version->fresh()->status);
        $this->actingAs($manager)->get(route('admin.spatial-datasets.index'))
            ->assertOk()
            ->assertSee('Reintentar preparación')
            ->assertDontSee('Pendiente de preparación técnica');
    }

    public function test_manager_can_retry_preparation_of_an_active_dataset(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $dataset = SpatialDataset::factory()->create(['status' => DatasetStatus::Active]);
        DatasetFormVersion::factory()->for($dataset, 'dataset')->create(['status' => DatasetFormVersionStatus::Published]);
        $materializer = $this->mock(MaterializeSpatialDataset::class);
        $materializer->shouldReceive('isAvailable')->once()->andReturnTrue();
        $materializer->shouldReceive('materialize')->once()->withArgs(fn (SpatialDataset $given): bool => $given->is($dataset));

        $this->actingAs($manager)->post(route('admin.spatial-datasets.materialization.store', $dataset))
            ->assertRedirect()->assertSessionHas('status');
    }

    public function test_unpublished_dataset_cannot_be_prepared(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $dataset = SpatialDataset::factory()->create();

        $this->actingAs($manager)->post(route('admin.spatial-datasets.materialization.store', $dataset))
            ->assertStatus(409);
    }

    public function test_management_support_sees_publication_action_without_field_editing_controls(): void
    {
        $support = User::factory()->create(['role' => UserRole::ManagementSupport]);
        $dataset = SpatialDataset::factory()->create();
        $version = DatasetFormVersion::factory()->for($dataset, 'dataset')->create();
        DatasetFormField::factory()->for($version, 'formVersion')->create();

        $this->actingAs($support)->get(route('admin.spatial-datasets.index'))
            ->assertOk()
            ->assertSee('Publicar versión')
            ->assertDontSee('Agregar campo al formulario')
            ->assertDontSee('Crear conjunto de datos');
    }

    public function test_approval_of_second_layer_updates_only_its_contract(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $firstDataset = SpatialDataset::factory()->create();
        $secondDataset = SpatialDataset::factory()->create();
        $version = DatasetFormVersion::factory()->for($secondDataset, 'dataset')->create();
        DatasetFormField::factory()->for($version, 'formVersion')->create();
        $import = SpatialImport::factory()->create([
            'status' => SpatialImportStatus::Approved,
            'spatial_dataset_id' => $firstDataset->id,
            'selected_table' => 'capa_inicial',
        ]);
        $secondContract = SpatialImportContract::factory()->create([
            'spatial_import_id' => $import->id,
            'source_table' => 'capa_nueva',
            'spatial_dataset_id' => $secondDataset->id,
            'status' => SpatialImportStatus::ContractDraft,
        ]);

        $this->actingAs($manager)->post(route('admin.spatial-datasets.versions.publication.store', [$secondDataset, $version]), [
            'effective_from' => '2026-09-16',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(SpatialImportStatus::Approved, $secondContract->fresh()->status);
        $this->assertSame($manager->id, $secondContract->fresh()->approved_by);
        $this->assertSame($firstDataset->id, $import->fresh()->spatial_dataset_id);
        $this->assertSame(SpatialImportStatus::Approved, $import->fresh()->status);
    }

    public function test_published_version_is_immutable_and_next_draft_clones_its_fields(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $dataset = SpatialDataset::factory()->create();
        $version = DatasetFormVersion::factory()->for($dataset, 'dataset')->create();
        $field = DatasetFormField::factory()->for($version, 'formVersion')->create([
            'key' => 'poblacion_afectada',
            'label' => 'Población afectada',
        ]);
        $import = SpatialImport::factory()->create([
            'status' => SpatialImportStatus::ContractDraft,
            'spatial_dataset_id' => $dataset->id,
        ]);

        $this->actingAs($manager)->post(route('admin.spatial-datasets.versions.publication.store', [$dataset, $version]), [
            'effective_from' => '2026-09-12',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(DatasetFormVersionStatus::Published, $version->fresh()->status);
        $this->assertSame($manager->id, $version->fresh()->approved_by);
        $this->assertSame(DatasetStatus::Active, $dataset->fresh()->status);
        $this->assertSame(SpatialImportStatus::Approved, $import->fresh()->status);
        $this->assertSame($manager->id, $import->fresh()->approved_by);
        $this->actingAs($admin)->patch(route('admin.spatial-datasets.versions.fields.update', [$dataset, $version, $field]), [
            ...$this->fieldPayload(),
            'label' => 'Contenido alterado',
        ])->assertForbidden();
        $this->assertSame('Población afectada', $field->fresh()->label);

        $this->actingAs($admin)
            ->post(route('admin.spatial-datasets.drafts.store', $dataset))
            ->assertRedirect()
            ->assertSessionHas('status');

        $draft = $dataset->versions()->where('version', 2)->firstOrFail();
        $this->assertSame(DatasetFormVersionStatus::Draft, $draft->status);
        $this->assertDatabaseHas('dataset_form_fields', [
            'dataset_form_version_id' => $draft->id,
            'key' => 'poblacion_afectada',
            'label' => 'Población afectada',
        ]);

        $inheritedField = $draft->fields()->where('key', 'poblacion_afectada')->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.spatial-datasets.versions.fields.update', [$dataset, $draft, $inheritedField]), [
            ...$this->fieldPayload(),
            'key' => 'personas_afectadas',
        ])->assertRedirect()->assertInvalid('key');
        $this->assertSame('poblacion_afectada', $inheritedField->fresh()->key);

        $this->actingAs($admin)->patch(route('admin.spatial-datasets.versions.fields.update', [$dataset, $draft, $inheritedField]), [
            ...$this->fieldPayload(),
            'key' => 'poblacion_afectada',
            'field_type' => 'decimal',
        ])->assertRedirect()->assertInvalid('field_type');
        $this->assertSame(DatasetFieldType::ShortText, $inheritedField->fresh()->field_type);

        $this->actingAs($admin)->post(route('admin.spatial-datasets.versions.fields.store', [$dataset, $draft]), [
            ...$this->fieldPayload(),
            'key' => 'profundidad_inundacion_m',
            'label' => 'Profundidad de inundación',
            'field_type' => 'decimal',
            'historical_policy' => 'optional_backfill',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('dataset_form_fields', [
            'dataset_form_version_id' => $draft->id,
            'key' => 'profundidad_inundacion_m',
            'introduced_in_version' => 2,
            'historical_policy' => 'optional_backfill',
        ]);
    }

    public function test_nested_field_from_another_dataset_returns_not_found(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $dataset = SpatialDataset::factory()->create();
        $otherVersion = DatasetFormVersion::factory()->create();

        $this->actingAs($admin)->post(route('admin.spatial-datasets.versions.fields.store', [$dataset, $otherVersion]), $this->fieldPayload())
            ->assertNotFound();
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldPayload(): array
    {
        return [
            'key' => 'descripcion',
            'label' => 'Descripción',
            'section' => 'Información general',
            'help_text' => 'Describa el hallazgo.',
            'field_type' => 'short_text',
            'unit' => null,
            'required' => 0,
            'options' => null,
            'min_value' => null,
            'max_value' => null,
            'max_length' => 500,
            'historical_policy' => 'future_only',
            'visible_in_qgis' => 1,
            'public_visible' => 0,
            'available_for_analytics' => 1,
            'sort_order' => 10,
        ];
    }
}
