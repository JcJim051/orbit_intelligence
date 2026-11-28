<?php

namespace Tests\Feature\Authorization;

use App\Enums\UserRole;
use App\Filament\Pages\Workspace;
use App\Models\IndicadorProducto;
use App\Models\IndicadorResultado;
use App\Models\MetaProducto;
use App\Models\MetaResultado;
use App\Models\Proyecto;
use App\Models\TipologiaInversion;
use App\Models\User;
use App\Policies\IntelligenceCatalogPolicy;
use App\Policies\ProyectoPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class InvestmentReviewerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_investment_role_has_scoped_reporting_and_full_pdd_access(): void
    {
        $user = User::factory()->make(['role' => UserRole::InvestmentReviewer]);

        $this->assertSame('Admin-inversión', UserRole::InvestmentReviewer->label());
        $this->assertTrue($user->canAccessManagementGoals());
        $this->assertFalse($user->veTodosLosSectores());
        $this->assertTrue($user->gestionaReporteSectorial());
        $this->assertTrue($user->canManageFullPddStructure());
        $this->assertTrue($user->canAccessMetaResultadoConstruccion());
    }

    public function test_role_can_edit_full_pdd_catalogs_but_not_delete(): void
    {
        $user = User::factory()->make(['role' => UserRole::InvestmentReviewer]);
        $admin = User::factory()->make(['role' => UserRole::Admin]);
        $policy = new IntelligenceCatalogPolicy;

        foreach ([
            [MetaProducto::class, new MetaProducto],
            [IndicadorProducto::class, new IndicadorProducto],
            [IndicadorResultado::class, new IndicadorResultado],
            [MetaResultado::class, new MetaResultado],
        ] as [$model, $record]) {
            $this->assertTrue($policy->create($user, $model));
            $this->assertTrue($policy->update($user, $record));
            $this->assertFalse($policy->delete($user, $record));
        }
        $this->assertTrue($policy->create($user, TipologiaInversion::class));
        $this->assertTrue($policy->update($user, new TipologiaInversion));
        $this->assertTrue($policy->create($admin, TipologiaInversion::class));
    }

    public function test_role_can_create_and_edit_projects_but_not_delete_them(): void
    {
        $user = User::factory()->make(['role' => UserRole::InvestmentReviewer]);
        $policy = new ProyectoPolicy;
        $project = new Proyecto;

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->update($user, $project));
        $this->assertFalse($policy->delete($user, $project));
    }

    public function test_reviewer_sees_create_and_edit_actions_in_meta_producto_catalog(): void
    {
        $user = User::factory()->create(['role' => UserRole::InvestmentReviewer]);
        $meta = MetaProducto::factory()->create();

        $this->assertSame(UserRole::InvestmentReviewer, $user->role);
        $this->assertTrue($user->canManageIntelligenceCatalogs());
        $this->assertInstanceOf(IntelligenceCatalogPolicy::class, Gate::getPolicyFor(MetaProducto::class));
        $this->assertTrue($user->can('create', [MetaProducto::class]));
        $this->assertTrue($user->can('create', MetaProducto::class));
        $this->assertTrue($user->can('update', $meta));

        $this->actingAs($user)
            ->followingRedirects()
            ->get(Workspace::getUrl(['workspace' => 'metas-producto']))
            ->assertOk()
            ->assertSee('Nuevo registro')
            ->assertSee('Editar')
            ->assertDontSee('Eliminar');

        $this->actingAs($user)
            ->followingRedirects()
            ->get(route('intelligence.metas-producto.create'))
            ->assertOk()
            ->assertSee('Guardar');

        $this->actingAs($user)
            ->followingRedirects()
            ->get(route('intelligence.metas-producto.edit', $meta))
            ->assertOk()
            ->assertSee('Guardar');
    }
}
