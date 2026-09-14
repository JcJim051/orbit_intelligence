<?php

namespace Tests\Feature\Investment;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\InvestmentEntity;
use App\Models\InvestmentEntityAssignment;
use App\Models\InvestmentFinancial;
use App\Models\InvestmentProject;
use App\Models\User;
use App\Services\Investments\InvestmentEntityClassifier;
use App\Services\Investments\InvestmentMetricsService;
use Database\Seeders\InvestmentEntitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestmentEntityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InvestmentEntitySeeder::class);
    }

    public function test_dashboard_always_shows_the_ten_official_entities(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('investments.dashboard'));

        $response->assertOk()
            ->assertSee('Agencia para la Infraestructura del Meta')
            ->assertSee('Empresa de Servicios Públicos del Meta')
            ->assertSee('Instituto de Turismo del Meta')
            ->assertSee('Unidad de Licores del Meta');
        $this->assertSame(10, $response->viewData('decentralizedEntities')->count());
    }

    public function test_classifier_recognizes_exact_and_textual_aliases(): void
    {
        $classifier = app(InvestmentEntityClassifier::class);

        $edesa = InvestmentProject::factory()->create(['executing_entity' => 'EDESA S.A. E.S.P.']);
        $aim = InvestmentProject::factory()->create(['name' => 'Obras ejecutadas por AIM en vías del Meta']);
        $tourism = InvestmentProject::factory()->create(['objective' => 'Fortalecer el Instituto Departamental de Turismo del Meta']);
        $culture = InvestmentProject::factory()->create(['name' => 'Dotación para el Instituto Departamental de Cultura del Meta']);

        foreach ([$edesa, $aim, $tourism, $culture] as $project) {
            $classifier->classify($project);
        }

        $this->assertDatabaseHas('investment_entity_assignments', [
            'investment_project_id' => $edesa->id,
            'investment_entity_id' => InvestmentEntity::where('slug', 'edesa')->value('id'),
            'status' => 'confirmed',
            'method' => 'automatic_exact',
        ]);
        foreach ([[$aim, 'aim'], [$tourism, 'instituto-turismo'], [$culture, 'instituto-cultura']] as [$project, $slug]) {
            $this->assertDatabaseHas('investment_entity_assignments', [
                'investment_project_id' => $project->id,
                'investment_entity_id' => InvestmentEntity::where('slug', $slug)->value('id'),
                'status' => 'suggested',
                'method' => 'automatic_text',
            ]);
        }
    }

    public function test_manual_confirmation_is_not_overwritten_by_reclassification(): void
    {
        $project = InvestmentProject::factory()->create(['executing_entity' => 'EDESA']);
        $entity = InvestmentEntity::where('slug', 'aim')->firstOrFail();
        $reviewer = User::factory()->create(['role' => UserRole::Admin]);
        $assignment = InvestmentEntityAssignment::factory()->create([
            'investment_project_id' => $project->id,
            'investment_entity_id' => $entity->id,
            'status' => 'confirmed',
            'role' => 'primary',
            'method' => 'manual_review',
            'reviewed_by' => $reviewer->id,
        ]);

        app(InvestmentEntityClassifier::class)->classify($project);

        $this->assertSame('confirmed', $assignment->fresh()->status);
        $this->assertSame('manual_review', $assignment->fresh()->method);
        $this->assertSame('primary', $assignment->fresh()->role);
    }

    public function test_only_admin_can_review_a_suggestion_and_the_change_is_audited(): void
    {
        $project = InvestmentProject::factory()->create();
        $aim = InvestmentEntity::where('slug', 'aim')->firstOrFail();
        $edesa = InvestmentEntity::where('slug', 'edesa')->firstOrFail();
        $assignment = InvestmentEntityAssignment::factory()->create([
            'investment_project_id' => $project->id,
            'investment_entity_id' => $aim->id,
            'status' => 'suggested',
        ]);
        $payload = ['decision' => 'confirm', 'investment_entity_id' => $edesa->id, 'role' => 'primary'];

        $this->actingAs(User::factory()->create())->patch(route('admin.investment-entity-assignments.update', $assignment), $payload)->assertForbidden();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->patch(route('admin.investment-entity-assignments.update', $assignment), $payload)->assertRedirect();

        $this->assertDatabaseHas('investment_entity_assignments', [
            'investment_project_id' => $project->id,
            'investment_entity_id' => $edesa->id,
            'status' => 'confirmed',
            'method' => 'manual_review',
        ]);
        $this->assertTrue(AuditLog::where('event', 'investment_entity_assignment_reviewed')->where('actor_id', $admin->id)->exists());
    }

    public function test_admin_can_classify_a_project_without_automatic_matches(): void
    {
        $project = InvestmentProject::factory()->create(['name' => 'Proyecto sin coincidencias conocidas']);
        $entity = InvestmentEntity::where('slug', 'idermeta')->firstOrFail();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('admin.investment-entities.index', ['status' => 'unclassified']))
            ->assertOk()->assertSee($project->bpin)->assertSee('Por clasificar');
        $this->actingAs($admin)->post(route('admin.investment-entity-assignments.store', $project), [
            'investment_entity_id' => $entity->id,
            'role' => 'primary',
        ])->assertRedirect();

        $this->assertDatabaseHas('investment_entity_assignments', [
            'investment_project_id' => $project->id,
            'investment_entity_id' => $entity->id,
            'status' => 'confirmed',
            'method' => 'manual_review',
        ]);
        $this->assertTrue(AuditLog::where('event', 'investment_entity_assignment_created')->where('actor_id', $admin->id)->exists());
    }

    public function test_collaborating_entities_do_not_duplicate_primary_entity_metrics(): void
    {
        $project = InvestmentProject::factory()->create();
        InvestmentFinancial::create([
            'investment_project_id' => $project->id,
            'source_dataset_id' => 'v4ap-cvae',
            'source_row_hash' => hash('sha256', $project->id.'-2026'),
            'fiscal_year' => 2026,
            'current_value' => 100,
            'paid_value' => 50,
            'raw_data' => [],
        ]);
        InvestmentEntityAssignment::factory()->create([
            'investment_project_id' => $project->id,
            'investment_entity_id' => InvestmentEntity::where('slug', 'aim')->value('id'),
            'status' => 'confirmed',
            'role' => 'primary',
        ]);
        InvestmentEntityAssignment::factory()->create([
            'investment_project_id' => $project->id,
            'investment_entity_id' => InvestmentEntity::where('slug', 'edesa')->value('id'),
            'status' => 'confirmed',
            'role' => 'collaborator',
        ]);

        $metrics = app(InvestmentMetricsService::class);

        $this->assertSame(1, $metrics->summary(['investment_entity' => 'aim'])['projects']);
        $this->assertSame(0, $metrics->summary(['investment_entity' => 'edesa'])['projects']);
        $this->assertSame(1, $metrics->summary([])['projects']);
    }
}
