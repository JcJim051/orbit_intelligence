<?php

namespace Tests\Feature\Admin;

use App\Enums\DatasetFormVersionStatus;
use App\Enums\UserRole;
use App\Models\DatasetFormField;
use App\Models\DatasetFormVersion;
use App\Models\GeoLayer;
use App\Models\SpatialDataset;
use App\Models\User;
use App\Services\Postgis\MaterializeSpatialDataset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class GeoLayerPublicAttributesControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_approver_can_select_published_point_attributes_without_creating_a_version(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        [$dataset, $layer, $version] = $this->managedLayer();
        DatasetFormField::factory()->for($version, 'formVersion')->create(['key' => 'nombre', 'label' => 'Nombre', 'public_visible' => true]);
        DatasetFormField::factory()->for($version, 'formVersion')->create(['key' => 'telefono', 'label' => 'Teléfono', 'public_visible' => true]);
        $this->mock(MaterializeSpatialDataset::class, function (MockInterface $mock) use ($dataset, $layer): void {
            $mock->shouldReceive('isAvailable')->once()->andReturnTrue();
            $mock->shouldReceive('updatePublicAttributes')->once()->withArgs(
                fn (SpatialDataset $givenDataset, GeoLayer $givenLayer, array $fields): bool => $givenDataset->is($dataset) && $givenLayer->is($layer) && $fields === ['nombre'],
            )->andReturnUsing(function (SpatialDataset $givenDataset, GeoLayer $givenLayer, array $fields): void {
                $givenLayer->update(['public_attribute_fields' => $fields]);
            });
        });

        $this->actingAs($admin)->post(route('admin.geo-layers.public-attributes.update', $layer), [
            'fields' => ['nombre'],
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(['nombre'], $layer->fresh()->public_attribute_fields);
        $this->assertSame(1, $dataset->versions()->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'geo_layer_public_attributes_updated', 'actor_id' => $admin->id]);
    }

    public function test_approver_can_hide_all_attributes(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        [$dataset, $layer, $version] = $this->managedLayer();
        DatasetFormField::factory()->for($version, 'formVersion')->create(['key' => 'nombre']);
        $this->mock(MaterializeSpatialDataset::class, function (MockInterface $mock) use ($dataset, $layer): void {
            $mock->shouldReceive('isAvailable')->once()->andReturnTrue();
            $mock->shouldReceive('updatePublicAttributes')->once()->withArgs(
                fn (SpatialDataset $givenDataset, GeoLayer $givenLayer, array $fields): bool => $givenDataset->is($dataset) && $givenLayer->is($layer) && $fields === [],
            )->andReturnUsing(function (SpatialDataset $givenDataset, GeoLayer $givenLayer): void {
                $givenLayer->update(['public_attribute_fields' => []]);
            });
        });

        $this->actingAs($admin)->post(route('admin.geo-layers.public-attributes.update', $layer), [])
            ->assertRedirect()->assertSessionHas('status');

        $this->assertSame([], $layer->fresh()->public_attribute_fields);
    }

    public function test_unpublished_field_cannot_be_selected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        [$dataset, $layer, $version] = $this->managedLayer();
        DatasetFormField::factory()->for($version, 'formVersion')->create(['key' => 'nombre']);

        $this->actingAs($admin)->post(route('admin.geo-layers.public-attributes.update', $layer), [
            'fields' => ['clave_privada'],
        ])->assertRedirect()->assertInvalid('fields.0');

        $this->assertNull($layer->fresh()->public_attribute_fields);
    }

    public function test_member_cannot_change_public_attributes(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);
        [$dataset, $layer] = $this->managedLayer();

        $this->actingAs($member)->post(route('admin.geo-layers.public-attributes.update', $layer), [])
            ->assertForbidden();

        $this->assertNull($layer->fresh()->public_attribute_fields);
    }

    public function test_external_layer_cannot_use_managed_attribute_controls(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $layer = GeoLayer::factory()->create();

        $this->actingAs($admin)->post(route('admin.geo-layers.public-attributes.update', $layer), [])
            ->assertNotFound();
    }

    public function test_layer_with_unprepared_published_version_cannot_change_attributes(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        [$dataset, $layer, $version] = $this->managedLayer();
        $dataset->update(['materialized_form_version' => 0]);

        $this->actingAs($admin)->post(route('admin.geo-layers.public-attributes.update', $layer), [])
            ->assertStatus(409);

        $this->assertNull($layer->fresh()->public_attribute_fields);
    }

    public function test_catalog_displays_attribute_checkboxes_for_a_managed_layer(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        [$dataset, $layer, $version] = $this->managedLayer();
        DatasetFormField::factory()->for($version, 'formVersion')->create(['key' => 'nombre', 'label' => 'Nombre del puesto', 'public_visible' => true]);

        $this->actingAs($admin)->get(route('admin.geo-viewers.index'))
            ->assertOk()
            ->assertSee('Atributos visibles al consultar un punto')
            ->assertSee('Nombre del puesto')
            ->assertSee('Guardar atributos visibles');
    }

    /** @return array{SpatialDataset, GeoLayer, DatasetFormVersion} */
    private function managedLayer(): array
    {
        $dataset = SpatialDataset::factory()->create([
            'slug' => 'centros-de-salud-meta',
            'physical_table' => 'centros_de_salud_meta',
            'materialized_form_version' => 1,
        ]);
        $layer = GeoLayer::factory()->create([
            'slug' => $dataset->slug,
            'source_url' => '/api/public/geodata/'.$dataset->slug,
            'public_attribute_fields' => null,
        ]);
        $version = DatasetFormVersion::factory()->for($dataset, 'dataset')->create([
            'status' => DatasetFormVersionStatus::Published,
        ]);

        return [$dataset, $layer, $version];
    }
}
