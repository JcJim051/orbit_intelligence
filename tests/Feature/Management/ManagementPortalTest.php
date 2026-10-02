<?php

namespace Tests\Feature\Management;

use App\Enums\GeoLayerAccessPolicy;
use App\Enums\GeoViewerStatus;
use App\Enums\UserRole;
use App\Filament\Clusters\Geography\Pages\CreateGeoViewer;
use App\Filament\Clusters\Geography\Pages\ManageGeoViewer;
use App\Filament\Pages\Workspace;
use App\Models\GeoLayer;
use App\Models\GeoViewer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ManagementPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_unauthenticated_user_is_sent_to_the_existing_login(): void
    {
        $this->get('/gestion')->assertRedirect(route('login'));
    }

    public function test_inactive_user_cannot_enter_the_management_panel(): void
    {
        $user = User::factory()->create(['active' => false]);

        $this->actingAs($user)->get('/gestion')->assertForbidden();
    }

    public function test_management_panel_is_rendered_in_spanish(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/gestion')
            ->assertOk()
            ->assertSee('lang="es"', false);
    }

    public function test_panel_logout_uses_the_existing_authentication_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/gestion/logout')->assertRedirect();
        $this->assertGuest();
    }

    public function test_home_remains_available_when_dashboard_tables_have_not_been_migrated(): void
    {
        $manager = User::factory()->create(['role' => UserRole::SiidManager]);
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('dashboard_versions');
        Schema::dropIfExists('dashboard_collaborators');
        Schema::dropIfExists('dashboards');
        Schema::dropIfExists('tabular_data_source_versions');
        Schema::dropIfExists('tabular_data_sources');
        Schema::enableForeignKeyConstraints();

        $this->actingAs($manager)->get('/gestion')
            ->assertOk()
            ->assertSee('Requiere migración');

        $this->actingAs($manager)->get('/gestion/dashboards')->assertOk();
    }

    public function test_login_and_application_root_open_the_role_based_home(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertRedirect('/gestion');

        $this->get('/')->assertRedirect('/gestion');
    }

    public function test_member_home_only_displays_general_work_modules(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($member)->get('/gestion')
            ->assertOk()
            ->assertSee('Actas y compromisos')
            ->assertSee('Inversión pública')
            ->assertDontSee('Inteligencia geográfica')
            ->assertDontSee('Dashboards interactivos')
            ->assertDontSee('Seguimiento a metas')
            ->assertDontSee('Administración de plataforma');
    }

    public function test_sidebar_exposes_direct_submenus_without_opening_each_module_home(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get('/gestion')
            ->assertOk()
            ->assertSee('siid-management-v2')
            ->assertSee('Consultar actas')
            ->assertSee('Registrar reunión')
            ->assertSee('Cargas desde QGIS')
            ->assertSee('Catálogo de datos')
            ->assertSee('Capas y geovisores')
            ->assertSee('Administrar tableros')
            ->assertSee('Fuentes tabulares')
            ->assertSee('Panorama')
            ->assertSee('Proyectos')
            ->assertSee('Equipo y permisos');
    }

    #[DataProvider('spatialAccessMatrix')]
    public function test_geography_module_preserves_the_spatial_role_matrix(UserRole $role, bool $allowed): void
    {
        $user = User::factory()->create(['role' => $role]);

        $response = $this->actingAs($user)->get('/gestion/geografia/inicio');

        $allowed ? $response->assertOk() : $response->assertForbidden();
    }

    /** @return array<string, array{UserRole, bool}> */
    public static function spatialAccessMatrix(): array
    {
        return [
            'usuario' => [UserRole::Member, false],
            'revisor' => [UserRole::Reviewer, false],
            'gestor SIID' => [UserRole::SiidManager, true],
            'apoyo de Gerencia' => [UserRole::ManagementSupport, true],
            'gerente' => [UserRole::Manager, true],
            'administrador' => [UserRole::Admin, true],
        ];
    }

    public function test_siid_manager_can_consult_owned_geographic_catalogs_but_not_qgis_authorizations(): void
    {
        $manager = User::factory()->create(['role' => UserRole::SiidManager]);

        $this->actingAs($manager)->get('/gestion/geografia/geovisores')->assertOk();
        $this->actingAs($manager)->get('/gestion/geografia/datos-abiertos')->assertOk();
        $this->actingAs($manager)->get('/gestion/geografia/cargas-qgis')->assertForbidden();
        $this->actingAs($manager)->get('/gestion/geografia/infraestructura')->assertForbidden();
    }

    public function test_administrator_can_open_every_geographic_pilot_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        foreach ([
            '/gestion/geografia/cargas-qgis',
            '/gestion/geografia/catalogo-datos',
            '/gestion/geografia/geovisores',
            '/gestion/geografia/datos-abiertos',
            '/gestion/geografia/infraestructura',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_geography_pages_do_not_repeat_the_cluster_menu_inside_the_content(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get('/gestion/geografia/inicio')
            ->assertOk()
            ->assertDontSee('fi-page-sub-navigation-sidebar', false)
            ->assertSee('Bandeja SIG');
    }

    public function test_open_data_wizard_is_rendered_inside_filament(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $workspaceUrl = Workspace::getUrl(['workspace' => 'fuentes-abiertas']);

        $this->actingAs($admin)->get($workspaceUrl)
            ->assertOk()
            ->assertSee('Crear visor desde Datos Abiertos')
            ->assertSee('data-open-data-wizard', false)
            ->assertDontSee('data-app-shell', false);

        $this->actingAs($admin)->get(route('admin.open-data-sources.index'))
            ->assertRedirect($workspaceUrl);
    }

    public function test_primary_module_workspaces_remain_inside_management_panel(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        foreach ([
            'actas',
            'actas-crear',
            'cargas-qgis',
            'catalogo-datos',
            'infraestructura',
            'tableros',
            'fuentes-tabulares',
            'inversion',
            'proyectos',
            'clasificaciones',
            'usuarios',
            'drive',
            'dispositivos',
        ] as $workspace) {
            $this->actingAs($admin)->get(Workspace::getUrl(['workspace' => $workspace]))
                ->assertOk()
                ->assertDontSee('data-app-shell', false);
        }
    }

    public function test_siid_manager_can_preview_an_owned_viewer_inside_the_management_panel(): void
    {
        $manager = User::factory()->create(['role' => UserRole::SiidManager]);
        $viewer = GeoViewer::factory()->create(['owner_id' => $manager->id]);

        $this->actingAs($manager)
            ->get('/gestion/geografia/geovisores/'.$viewer->slug.'/previsualizar')
            ->assertOk()
            ->assertSee($viewer->name)
            ->assertSee(route('admin.geo-viewers.preview', $viewer), false);
    }

    public function test_siid_manager_cannot_preview_another_managers_viewer(): void
    {
        $manager = User::factory()->create(['role' => UserRole::SiidManager]);
        $viewer = GeoViewer::factory()->create(['owner_id' => User::factory()->create()->id]);

        $this->actingAs($manager)
            ->get('/gestion/geografia/geovisores/'.$viewer->slug.'/previsualizar')
            ->assertForbidden();
    }

    public function test_geoviewer_can_be_configured_and_published_without_leaving_management_panel(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $viewer = GeoViewer::factory()->create(['owner_id' => $admin->id]);
        $layer = GeoLayer::factory()->create([
            'active' => true,
            'access_policy' => GeoLayerAccessPolicy::Downloadable,
        ]);
        $viewer->layers()->attach($layer, [
            'sort_order' => 10,
            'visible_by_default' => true,
            'show_in_legend' => true,
            'opacity' => 1,
        ]);
        $managementUrl = ManageGeoViewer::getUrl(['geoViewer' => $viewer]);

        $this->actingAs($admin)->get($managementUrl)
            ->assertOk()
            ->assertSee('Capas del geovisor')
            ->assertSee('Enviar a revisión')
            ->assertSee(route('admin.geo-viewers.publication.store', $viewer), false);

        $this->actingAs($admin)
            ->from($managementUrl)
            ->post(route('admin.geo-viewers.publication.store', $viewer))
            ->assertRedirect($managementUrl)
            ->assertSessionHas('status');

        $this->assertSame(GeoViewerStatus::Published, $viewer->fresh()->status);
        $this->actingAs($admin)->get($managementUrl)
            ->assertOk()
            ->assertSee('Publicación activa')
            ->assertSee(route('geo-viewers.embed', $viewer), false);
    }

    public function test_siid_manager_creates_a_viewer_and_continues_in_the_management_panel(): void
    {
        $manager = User::factory()->create(['role' => UserRole::SiidManager]);

        $this->actingAs($manager)->get(CreateGeoViewer::getUrl())
            ->assertOk()
            ->assertSee('Crear y configurar capas');

        $response = $this->actingAs($manager)->post(route('admin.geo-viewers.store'), [
            'management_panel' => 1,
            'name' => 'Visor territorial de prueba',
            'slug' => 'visor-territorial-prueba',
            'description' => 'Validación del flujo interno.',
            'center_latitude' => 4.15,
            'center_longitude' => -73.63,
            'initial_zoom' => 8,
            'status' => 'draft',
        ]);

        $viewer = GeoViewer::query()->where('slug', 'visor-territorial-prueba')->firstOrFail();
        $response->assertRedirect(ManageGeoViewer::getUrl(['geoViewer' => $viewer]));
        $this->assertSame($manager->id, $viewer->owner_id);
    }

    public function test_siid_manager_cannot_manage_another_managers_viewer(): void
    {
        $manager = User::factory()->create(['role' => UserRole::SiidManager]);
        $viewer = GeoViewer::factory()->create(['owner_id' => User::factory()->create()->id]);

        $this->actingAs($manager)
            ->get(ManageGeoViewer::getUrl(['geoViewer' => $viewer]))
            ->assertForbidden();
    }

    public function test_goals_and_platform_administration_are_restricted_by_role(): void
    {
        $support = User::factory()->create(['role' => UserRole::ManagementSupport]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $siidManager = User::factory()->create(['role' => UserRole::SiidManager]);

        $this->actingAs($support)->get('/gestion/metas')->assertOk();
        $this->actingAs($support)->get('/gestion/administracion')->assertForbidden();
        $this->actingAs($manager)->get('/gestion/administracion')->assertOk();
        $this->actingAs($siidManager)->get('/gestion/metas')->assertForbidden();
    }
}
