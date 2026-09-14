<?php

namespace Tests\Feature\Admin;

use App\Enums\DatasetFormVersionStatus;
use App\Enums\DatasetStatus;
use App\Enums\UserRole;
use App\Models\DatasetFormVersion;
use App\Models\SpatialDataset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpatialDatasetControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_cannot_access_spatial_data_catalog(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($member)
            ->get(route('admin.spatial-datasets.index'))
            ->assertForbidden();
    }

    public function test_admin_creates_dataset_with_first_draft_version_and_audit_record(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('admin.spatial-datasets.store'), [
            'name' => 'Puntos críticos',
            'slug' => 'puntos-criticos',
            'sector' => 'Gestión del Riesgo',
            'description' => 'Registro institucional.',
            'geometry_type' => 'point',
            'status' => 'active',
        ])->assertRedirect()->assertSessionHas('status');

        $dataset = SpatialDataset::query()->where('slug', 'puntos-criticos')->firstOrFail();
        $this->assertSame(DatasetStatus::Draft, $dataset->status);
        $this->assertSame($admin->id, $dataset->created_by);
        $this->assertDatabaseHas('dataset_form_versions', [
            'spatial_dataset_id' => $dataset->id,
            'version' => 1,
            'status' => DatasetFormVersionStatus::Draft->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'event' => 'spatial_dataset_created',
            'stage' => 'data_catalog',
        ]);
    }

    public function test_admin_panel_escapes_dataset_content(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        SpatialDataset::factory()->create([
            'name' => '<script>alert("dataset")</script>',
            'description' => '<img src=x onerror=alert(1)>',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.spatial-datasets.index'))
            ->assertOk()
            ->assertDontSee('<script>alert("dataset")</script>', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_structural_identity_cannot_change_after_a_form_was_published(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $dataset = SpatialDataset::factory()->create(['slug' => 'puntos-criticos', 'geometry_type' => 'point']);
        DatasetFormVersion::factory()->create([
            'spatial_dataset_id' => $dataset->id,
            'status' => DatasetFormVersionStatus::Published,
        ]);

        $this->actingAs($admin)->patch(route('admin.spatial-datasets.update', $dataset), [
            'name' => $dataset->name,
            'slug' => 'puntos-renombrados',
            'sector' => $dataset->sector,
            'description' => $dataset->description,
            'geometry_type' => 'polygon',
        ])->assertRedirect()->assertInvalid(['slug', 'geometry_type']);

        $this->assertSame('puntos-criticos', $dataset->fresh()->slug);
        $this->assertSame('point', $dataset->fresh()->geometry_type);
    }
}
