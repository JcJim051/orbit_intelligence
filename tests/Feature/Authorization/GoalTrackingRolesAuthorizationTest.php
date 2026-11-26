<?php

namespace Tests\Feature\Authorization;

use App\Enums\UserRole;
use App\Filament\Pages\Workspace;
use App\Models\Dependencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalTrackingRolesAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_tracking_user_only_sees_and_opens_assigned_dependencies(): void
    {
        $assigned = Dependencia::factory()->create(['nombre' => 'Dependencia asignada']);
        $other = Dependencia::factory()->create(['nombre' => 'Dependencia restringida']);
        $user = User::factory()->create(['role' => UserRole::GoalTracking]);
        $user->dependencias()->attach($assigned);

        $this->actingAs($user)
            ->get('/gestion')
            ->assertOk()
            ->assertSee('Seguimiento a metas')
            ->assertSee('Dependencias')
            ->assertDontSee(Workspace::getUrl(['workspace' => 'reporte-mensual']), false)
            ->assertDontSee(Workspace::getUrl(['workspace' => 'pilares']), false)
            ->assertDontSee(Workspace::getUrl(['workspace' => 'actas']), false)
            ->assertDontSee(Workspace::getUrl(['workspace' => 'inversion']), false);

        $this->actingAs($user)
            ->get(Workspace::getUrl(['workspace' => 'seguimiento-dependencias']))
            ->assertOk()
            ->assertSee('Dependencia asignada')
            ->assertDontSee('Dependencia restringida');

        $this->actingAs($user)
            ->get(Workspace::getUrl(['workspace' => 'seguimiento-dependencia', 'record' => $assigned->id]))
            ->assertOk();

        $this->actingAs($user)
            ->get(Workspace::getUrl(['workspace' => 'seguimiento-dependencia', 'record' => $other->id]))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(Workspace::getUrl(['workspace' => 'reporte-mensual']))
            ->assertForbidden();
    }

    public function test_admin_investment_is_scoped_and_manager_investment_is_global(): void
    {
        $assigned = Dependencia::factory()->create(['nombre' => 'Dependencia asignada']);
        $other = Dependencia::factory()->create(['nombre' => 'Dependencia global']);
        $adminInvestment = User::factory()->create(['role' => UserRole::InvestmentReviewer]);
        $adminInvestment->dependencias()->attach($assigned);
        $managerInvestment = User::factory()->create(['role' => UserRole::InvestmentManager]);

        $this->assertTrue($adminInvestment->puedeAccederDependenciaSeguimiento($assigned->id));
        $this->assertFalse($adminInvestment->puedeAccederDependenciaSeguimiento($other->id));
        $this->assertTrue($adminInvestment->puedeRevisarDependenciaSeguimiento($assigned->id));
        $this->assertFalse($adminInvestment->puedeRevisarDependenciaSeguimiento($other->id));
        $this->assertTrue($managerInvestment->puedeAccederDependenciaSeguimiento($assigned->id));
        $this->assertTrue($managerInvestment->puedeAccederDependenciaSeguimiento($other->id));

        $this->actingAs($adminInvestment)
            ->get(Workspace::getUrl(['workspace' => 'seguimiento-dependencia', 'record' => $other->id]))
            ->assertForbidden();

        $this->actingAs($managerInvestment)
            ->get(Workspace::getUrl(['workspace' => 'seguimiento-dependencia', 'record' => $other->id]))
            ->assertOk();
    }

    public function test_scoped_assignments_are_preserved_when_role_changes(): void
    {
        $dependencia = Dependencia::factory()->create();
        $user = User::factory()->create(['role' => UserRole::GoalTracking]);
        $user->dependencias()->attach($dependencia);

        $user->update(['role' => UserRole::Member]);

        $this->assertTrue($user->fresh()->dependencias()->whereKey($dependencia->id)->exists());
    }
}
