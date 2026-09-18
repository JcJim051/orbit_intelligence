<?php

namespace Tests\Feature\Admin;

use App\Enums\DatasetFormVersionStatus;
use App\Enums\UserRole;
use App\Models\DatasetFormField;
use App\Models\DatasetFormVersion;
use App\Models\SpatialDataset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatasetFormPublicFieldsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_selects_all_draft_fields_for_publication_without_changing_the_published_version(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $dataset = SpatialDataset::factory()->create();
        $published = DatasetFormVersion::factory()->for($dataset, 'dataset')->create([
            'status' => DatasetFormVersionStatus::Published,
        ]);
        $publishedField = DatasetFormField::factory()->for($published, 'formVersion')->create(['public_visible' => false]);
        $draft = DatasetFormVersion::factory()->for($dataset, 'dataset')->create(['version' => 2]);
        $first = DatasetFormField::factory()->for($draft, 'formVersion')->create(['public_visible' => false]);
        $second = DatasetFormField::factory()->for($draft, 'formVersion')->create(['public_visible' => false]);

        $this->actingAs($admin)->get(route('admin.spatial-datasets.index'))
            ->assertOk()
            ->assertSee('Seleccionar todos los atributos para la ficha pública');

        $this->actingAs($admin)->post(route('admin.spatial-datasets.versions.public-fields.store', [$dataset, $draft]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertTrue($first->fresh()->public_visible);
        $this->assertTrue($second->fresh()->public_visible);
        $this->assertFalse($publishedField->fresh()->public_visible);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'dataset_form_public_fields_enabled',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_non_admin_cannot_select_public_fields(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $dataset = SpatialDataset::factory()->create();
        $draft = DatasetFormVersion::factory()->for($dataset, 'dataset')->create();
        $field = DatasetFormField::factory()->for($draft, 'formVersion')->create(['public_visible' => false]);

        $this->actingAs($manager)->post(route('admin.spatial-datasets.versions.public-fields.store', [$dataset, $draft]))
            ->assertForbidden();

        $this->assertFalse($field->fresh()->public_visible);
    }

    public function test_published_version_cannot_be_reclassified_by_bulk_action(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $dataset = SpatialDataset::factory()->create();
        $published = DatasetFormVersion::factory()->for($dataset, 'dataset')->create([
            'status' => DatasetFormVersionStatus::Published,
        ]);
        $field = DatasetFormField::factory()->for($published, 'formVersion')->create(['public_visible' => false]);

        $this->actingAs($admin)->post(route('admin.spatial-datasets.versions.public-fields.store', [$dataset, $published]))
            ->assertStatus(409);

        $this->assertFalse($field->fresh()->public_visible);
    }

    public function test_version_from_another_dataset_is_not_found(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $dataset = SpatialDataset::factory()->create();
        $otherVersion = DatasetFormVersion::factory()->create();

        $this->actingAs($admin)->post(route('admin.spatial-datasets.versions.public-fields.store', [$dataset, $otherVersion]))
            ->assertNotFound();
    }
}
