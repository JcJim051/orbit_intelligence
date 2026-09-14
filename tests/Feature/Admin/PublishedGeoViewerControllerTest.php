<?php

namespace Tests\Feature\Admin;

use App\Enums\GeoLayerAccessPolicy;
use App\Enums\GeoViewerStatus;
use App\Enums\UserRole;
use App\Models\GeoLayer;
use App\Models\GeoViewer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublishedGeoViewerControllerTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('approvalRoles')]
    public function test_authorized_management_role_approves_viewer_publication(UserRole $role): void
    {
        $approver = User::factory()->create(['role' => $role]);
        $viewer = GeoViewer::factory()->create(['status' => GeoViewerStatus::Draft]);
        $layer = GeoLayer::factory()->create(['active' => true]);
        $viewer->layers()->attach($layer, [
            'sort_order' => 10, 'visible_by_default' => true, 'show_in_legend' => true, 'opacity' => 1,
        ]);

        $this->actingAs($approver)->post(route('admin.geo-viewers.publication.store', $viewer))
            ->assertRedirect()->assertSessionHas('status');

        $viewer->refresh();
        $this->assertSame(GeoViewerStatus::Published, $viewer->status);
        $this->assertSame($approver->id, $viewer->approved_by);
        $this->assertNotNull($viewer->published_at);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $approver->id, 'event' => 'geo_viewer_published', 'stage' => 'geovisors',
        ]);
    }

    public function test_viewer_without_available_layers_remains_draft(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $viewer = GeoViewer::factory()->create(['status' => GeoViewerStatus::Draft]);

        $this->actingAs($manager)->post(route('admin.geo-viewers.publication.store', $viewer))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(GeoViewerStatus::Draft, $viewer->fresh()->status);
        $this->assertNull($viewer->fresh()->approved_by);
    }

    public function test_viewer_with_unclassified_layer_cannot_be_published(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $viewer = GeoViewer::factory()->create(['status' => GeoViewerStatus::Draft]);
        $layer = GeoLayer::factory()->create(['access_policy' => GeoLayerAccessPolicy::Pending]);
        $viewer->layers()->attach($layer, ['sort_order' => 10]);

        $this->actingAs($admin)->post(route('admin.geo-viewers.publication.store', $viewer))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(GeoViewerStatus::Draft, $viewer->fresh()->status);
    }

    public function test_manager_sees_approval_action_without_technical_edit_forms(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        GeoViewer::factory()->create(['status' => GeoViewerStatus::Draft]);

        $this->actingAs($manager)->get(route('admin.geo-viewers.index'))
            ->assertOk()
            ->assertSee('Aprobar y publicar geovisor')
            ->assertDontSee('Guardar y aplicar configuración')
            ->assertDontSee('Registrar capa geográfica');
    }

    /** @return array<string, array{UserRole}> */
    public static function approvalRoles(): array
    {
        return [
            'administrador técnico' => [UserRole::Admin],
            'gerente' => [UserRole::Manager],
            'apoyo administrativo' => [UserRole::ManagementSupport],
        ];
    }
}
