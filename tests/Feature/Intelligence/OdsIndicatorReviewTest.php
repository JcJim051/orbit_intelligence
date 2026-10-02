<?php

namespace Tests\Feature\Intelligence;

use App\Enums\UserRole;
use App\Filament\Pages\Workspace;
use App\Models\IndicadorResultado;
use App\Models\IndicadorResultadoOdsComment;
use App\Models\IndicadorResultadoOdsLink;
use App\Models\IndicadorResultadoOdsReview;
use App\Models\MetaResultado;
use App\Models\OdsGoal;
use App\Models\OdsIndicator;
use App\Models\OdsTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OdsIndicatorReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_equipo_revisa_indicadores_resultado_con_comentarios_y_trazabilidad(): void
    {
        $this->withoutVite();

        $coordinador = User::factory()->create(['role' => UserRole::Manager]);
        $revisor = User::factory()->create(['role' => UserRole::SiidManager, 'name' => 'Revisor ODS']);
        $indicador = IndicadorResultado::factory()->create([
            'nombre' => 'Cobertura de atención integral en salud',
            'unidad_medida' => 'Porcentaje',
        ]);

        $this->actingAs($coordinador)
            ->get(Workspace::getUrl(['workspace' => 'revision-ods']))
            ->assertOk()
            ->assertSee('Revisión humana ODS')
            ->assertSee('Cobertura de atención integral en salud');

        $task = IndicadorResultadoOdsReview::query()->where('indicador_resultado_id', $indicador->id)->sole();

        $this->patch(route('intelligence.revision-ods.update', $task), [
            'assigned_to' => $revisor->id,
            'status' => 'in_review',
            'comment' => 'Asignado para revisión temática de salud.',
        ])->assertRedirect();

        $task->refresh();
        $this->assertSame($revisor->id, $task->assigned_to);
        $this->assertSame('in_review', $task->status);

        $this->post(route('intelligence.revision-ods.links.store', $task), [
            'ods_goal_code' => '3',
            'ods_goal_name' => 'Salud y bienestar',
            'ods_target_code' => '3.8',
            'ods_target_name' => 'Lograr cobertura sanitaria universal',
            'ods_indicator_code' => '3.8.1',
            'ods_indicator_name' => 'Cobertura de servicios esenciales de salud',
            'ods_indicator_unit' => 'Porcentaje',
            'relation_type' => 'direct',
            'confidence' => 'high',
            'status' => 'accepted',
            'justification' => 'El indicador territorial mide cobertura de servicios de salud y corresponde directamente con la lógica de cobertura sanitaria universal.',
            'comment' => 'Relación validada con alta confianza.',
        ])->assertRedirect();

        $link = IndicadorResultadoOdsLink::query()->sole();
        $this->assertSame($indicador->id, $link->indicador_resultado_id);
        $this->assertSame('direct', $link->relation_type);
        $this->assertSame('accepted', $link->status);
        $this->assertSame($coordinador->id, $link->created_by);
        $this->assertDatabaseHas('ods_indicators', ['code' => '3.8.1']);

        $this->post(route('intelligence.revision-ods.comments.store', $task), [
            'comment' => 'Queda pendiente revisar si también aplica para ODS 10 por enfoque diferencial.',
        ])->assertRedirect();

        $this->assertSame(3, IndicadorResultadoOdsComment::query()->count());

        $this->get(Workspace::getUrl(['workspace' => 'revision-ods-detalle', 'record' => $task->id]))
            ->assertOk()
            ->assertSee('Cobertura de servicios esenciales de salud')
            ->assertSee('Relación validada con alta confianza.')
            ->assertSee('pendiente revisar si también aplica', false);
    }

    public function test_usuario_basico_no_accede_a_revision_ods(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Member]))
            ->get(route('intelligence.revision-ods.index'))
            ->assertForbidden();
    }

    public function test_genera_sugerencias_ods_como_propuestas_con_trazabilidad(): void
    {
        $this->withoutVite();

        $user = User::factory()->create(['role' => UserRole::SiidManager]);
        $indicador = IndicadorResultado::factory()->create([
            'nombre' => 'Cobertura en salud materna',
            'unidad_medida' => 'Porcentaje',
        ]);
        MetaResultado::factory()->create([
            'indicador_resultado_id' => $indicador->id,
            'descripcion' => 'Aumentar la cobertura de atención en salud materna y servicios esenciales.',
        ]);

        $goal = OdsGoal::query()->create(['code' => '3', 'name' => 'Salud y bienestar']);
        $target = OdsTarget::query()->create(['ods_goal_id' => $goal->id, 'code' => '3.8', 'name' => 'Lograr cobertura sanitaria universal']);
        OdsIndicator::query()->create([
            'ods_target_id' => $target->id,
            'code' => '3.8.1.C',
            'name' => 'Cobertura de servicios esenciales de salud',
            'description' => 'Mide la cobertura de servicios esenciales de salud, incluyendo atención materna.',
            'active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('intelligence.revision-ods.suggest'))
            ->assertRedirect(Workspace::getUrl(['workspace' => 'revision-ods']));

        $link = IndicadorResultadoOdsLink::query()->with('odsIndicator')->sole();
        $this->assertSame($indicador->id, $link->indicador_resultado_id);
        $this->assertSame('proposed', $link->status);
        $this->assertContains($link->relation_type, ['direct', 'partial', 'contextual']);
        $this->assertContains($link->confidence, ['high', 'medium', 'low']);
        $this->assertStringContainsString('Sugerencia automática', $link->justification);
        $this->assertSame('3.8.1.C', $link->odsIndicator->code);
        $this->assertDatabaseHas('indicador_resultado_ods_comments', [
            'event_type' => 'auto_suggestion',
            'user_id' => $user->id,
        ]);
        $this->assertSame('in_review', IndicadorResultadoOdsReview::query()->sole()->status);
    }

    public function test_revisor_rechaza_y_validador_confirma_sugerencias_ods(): void
    {
        $this->withoutVite();

        $revisor = User::factory()->create(['role' => UserRole::OdsReviewer]);
        $validador = User::factory()->create(['role' => UserRole::OdsValidator]);
        $indicador = IndicadorResultado::factory()->create(['nombre' => 'Aumento de la cobertura del sistema acueducto']);
        $task = IndicadorResultadoOdsReview::query()->create([
            'indicador_resultado_id' => $indicador->id,
            'assigned_to' => $revisor->id,
        ]);
        $goal = OdsGoal::query()->create(['code' => '6', 'name' => 'Agua limpia y saneamiento']);
        $target = OdsTarget::query()->create(['ods_goal_id' => $goal->id, 'code' => '6.1', 'name' => 'Acceso a agua potable']);
        $ods = OdsIndicator::query()->create([
            'ods_target_id' => $target->id,
            'code' => '6.1.1.P',
            'name' => 'Acceso a agua potable',
            'active' => true,
        ]);

        $link = IndicadorResultadoOdsLink::query()->create([
            'review_id' => $task->id,
            'indicador_resultado_id' => $indicador->id,
            'ods_indicator_id' => $ods->id,
            'relation_type' => 'partial',
            'confidence' => 'medium',
            'status' => 'proposed',
            'justification' => 'Sugerencia automática para revisar.',
            'created_by' => $revisor->id,
        ]);

        $this->actingAs($revisor)
            ->patch(route('intelligence.revision-ods.links.update', [$task, $link]), [
                'decision' => 'accept',
                'comment' => 'Intento de confirmar sin rol validador.',
            ])
            ->assertForbidden();

        $this->actingAs($revisor)
            ->patch(route('intelligence.revision-ods.links.update', [$task, $link]), [
                'decision' => 'reject',
                'comment' => 'No corresponde porque la medición territorial no es comparable.',
            ])
            ->assertRedirect();

        $link->refresh();
        $this->assertSame('rejected', $link->status);
        $this->assertSame($revisor->id, $link->reviewed_by);
        $this->assertDatabaseHas('indicador_resultado_ods_comments', [
            'link_id' => $link->id,
            'user_id' => $revisor->id,
            'event_type' => 'link_rejected',
        ]);

        $odsAccepted = OdsIndicator::query()->create([
            'ods_target_id' => $target->id,
            'code' => '6.1.2.P',
            'name' => 'Acceso a agua potable urbano',
            'active' => true,
        ]);
        $accepted = IndicadorResultadoOdsLink::query()->create([
            'review_id' => $task->id,
            'indicador_resultado_id' => $indicador->id,
            'ods_indicator_id' => $odsAccepted->id,
            'relation_type' => 'direct',
            'confidence' => 'high',
            'status' => 'proposed',
            'justification' => 'Nueva propuesta revisada.',
        ]);

        $this->actingAs($validador)
            ->patch(route('intelligence.revision-ods.links.update', [$task, $accepted]), [
                'decision' => 'accept',
                'comment' => 'Confirmada por equivalencia temática y de medición.',
            ])
            ->assertRedirect();

        $accepted->refresh();
        $task->refresh();
        $this->assertSame('accepted', $accepted->status);
        $this->assertSame($validador->id, $accepted->reviewed_by);
        $this->assertSame('completed', $task->status);
    }

    public function test_revisor_ods_solo_ve_tareas_asignadas_y_no_otros_modulos(): void
    {
        $this->withoutVite();

        $revisor = User::factory()->create(['role' => UserRole::OdsReviewer]);
        $otroRevisor = User::factory()->create(['role' => UserRole::OdsReviewer]);
        $asignado = IndicadorResultado::factory()->create(['nombre' => 'Indicador asignado al revisor']);
        $noAsignado = IndicadorResultado::factory()->create(['nombre' => 'Indicador de otro revisor']);

        $taskAsignada = IndicadorResultadoOdsReview::query()->create([
            'indicador_resultado_id' => $asignado->id,
            'assigned_to' => $revisor->id,
        ]);
        $taskNoAsignada = IndicadorResultadoOdsReview::query()->create([
            'indicador_resultado_id' => $noAsignado->id,
            'assigned_to' => $otroRevisor->id,
        ]);

        $this->actingAs($revisor)
            ->get(Workspace::getUrl(['workspace' => 'revision-ods']))
            ->assertOk()
            ->assertSee('Indicador asignado al revisor')
            ->assertDontSee('Indicador de otro revisor');

        $this->actingAs($revisor)
            ->get(Workspace::getUrl(['workspace' => 'revision-ods-detalle', 'record' => $taskAsignada->id]))
            ->assertOk();

        $this->actingAs($revisor)
            ->get(Workspace::getUrl(['workspace' => 'revision-ods-detalle', 'record' => $taskNoAsignada->id]))
            ->assertForbidden();

        $this->actingAs($revisor)
            ->get(Workspace::getUrl(['workspace' => 'reporte-mensual']))
            ->assertForbidden();

        $this->actingAs($revisor)
            ->post(route('intelligence.revision-ods.suggest'))
            ->assertForbidden();
    }

    public function test_reparte_indicadores_pendientes_al_equipo_ods_sin_reasignar_existentes(): void
    {
        $this->withoutVite();

        $coordinador = User::factory()->create(['role' => UserRole::Manager]);
        $luisa = User::factory()->create(['name' => 'Luisa Martínez', 'role' => UserRole::Member]);
        $diana = User::factory()->create(['name' => 'Diana Gómez', 'role' => UserRole::Member]);
        $braian = User::factory()->create(['name' => 'Braian Torres', 'role' => UserRole::Member]);
        $fabian = User::factory()->create(['name' => 'Fabian Rojas', 'role' => UserRole::Member]);
        $clara = User::factory()->create(['name' => 'Clara Pérez', 'role' => UserRole::Member]);
        $bibiana = User::factory()->create(['name' => 'Bibiana López', 'role' => UserRole::Member]);

        $indicadores = IndicadorResultado::factory()->count(11)->create();

        IndicadorResultadoOdsReview::query()->create([
            'indicador_resultado_id' => $indicadores->first()->id,
            'assigned_to' => $luisa->id,
            'status' => 'in_review',
        ]);

        $this->actingAs($coordinador)
            ->post(route('intelligence.revision-ods.assign-team'))
            ->assertRedirect(Workspace::getUrl(['workspace' => 'revision-ods']));

        $this->assertSame(UserRole::OdsReviewer, $luisa->refresh()->role);
        $this->assertSame(UserRole::OdsReviewer, $diana->refresh()->role);
        $this->assertSame(UserRole::OdsReviewer, $braian->refresh()->role);
        $this->assertSame(UserRole::OdsReviewer, $fabian->refresh()->role);
        $this->assertSame(UserRole::OdsReviewer, $clara->refresh()->role);
        $this->assertSame(UserRole::OdsValidator, $bibiana->refresh()->role);

        $this->assertSame(11, IndicadorResultadoOdsReview::query()->count());
        $this->assertSame($luisa->id, IndicadorResultadoOdsReview::query()
            ->where('indicador_resultado_id', $indicadores->first()->id)
            ->value('assigned_to'));

        $counts = IndicadorResultadoOdsReview::query()
            ->whereIn('assigned_to', [$luisa->id, $diana->id, $braian->id, $fabian->id, $clara->id])
            ->selectRaw('assigned_to, count(*) as total')
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        $this->assertSame([2, 2, 2, 2, 3], $counts->values()->sort()->values()->all());
        $this->assertSame(0, IndicadorResultadoOdsReview::query()->where('assigned_to', $bibiana->id)->count());
        $this->assertSame(10, IndicadorResultadoOdsComment::query()->where('event_type', 'auto_assignment')->count());
    }
}
