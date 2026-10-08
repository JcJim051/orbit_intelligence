<?php

namespace Tests\Feature\Intelligence;

use App\Enums\OrientacionIndicador;
use App\Enums\UserRole;
use App\Filament\Pages\Workspace;
use App\Models\IndicadorResultado;
use App\Models\MetaResultado;
use App\Models\MetaResultadoConstruccion;
use App\Models\MetaResultadoConstruccionComentario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetaResultadoConstruccionTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_bandeja_de_construccion_y_guarda_medicion_resultado(): void
    {
        $this->withoutVite();

        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $reviewer = User::factory()->create(['role' => UserRole::OdsReviewer, 'name' => 'Luisa Revisión']);
        $indicador = IndicadorResultado::factory()->create([
            'nombre' => 'Cobertura de servicios sociales',
            'orientacion' => OrientacionIndicador::Incremento,
            'linea_base' => 10,
            'meta_cuatrienio' => 70,
        ]);
        $meta = MetaResultado::factory()->create([
            'codigo_provisional' => 'MR-101',
            'descripcion' => 'Aumentar la cobertura social territorial',
            'indicador_resultado_id' => $indicador->id,
        ]);
        $this->actingAs($manager)
            ->get(Workspace::getUrl(['workspace' => 'construccion-metas-resultado']))
            ->assertOk()
            ->assertSee('Construcción metas resultado')
            ->assertSee('Aumentar la cobertura social territorial');

        $task = MetaResultadoConstruccion::query()->where('meta_resultado_id', $meta->id)->sole();

        $this->patch(route('intelligence.construccion-metas-resultado.update', $task), [
            'assigned_to' => $reviewer->id,
            'status' => 'pending_validation',
            'measurement_mode' => 'dual',
            'baseline_value' => 10,
            'current_value' => 40,
            'current_value_date' => '2026-10-06',
            'current_value_source' => 'Fuente oficial de seguimiento',
            'methodology_notes' => 'Se compara línea base contra valor actual y meta cuatrienio.',
            'comment' => 'Listo para validación.',
        ])->assertRedirect();

        $task->refresh();
        $this->assertSame($reviewer->id, $task->assigned_to);
        $this->assertSame('pending_validation', $task->status);
        $this->assertSame(50.0, (float) $task->result_progress_pct);
        $this->assertSame(1, MetaResultadoConstruccionComentario::query()->count());
    }

    public function test_revisor_ods_solo_ve_metas_resultado_asignadas(): void
    {
        $this->withoutVite();

        $reviewer = User::factory()->create(['role' => UserRole::OdsReviewer]);
        $other = User::factory()->create(['role' => UserRole::OdsReviewer]);

        $assignedMeta = MetaResultado::factory()->create(['descripcion' => 'Meta visible del revisor']);
        $hiddenMeta = MetaResultado::factory()->create(['descripcion' => 'Meta de otro revisor']);

        $assignedTask = MetaResultadoConstruccion::query()->create([
            'meta_resultado_id' => $assignedMeta->id,
            'assigned_to' => $reviewer->id,
            'status' => 'in_review',
        ]);
        $hiddenTask = MetaResultadoConstruccion::query()->create([
            'meta_resultado_id' => $hiddenMeta->id,
            'assigned_to' => $other->id,
            'status' => 'in_review',
        ]);

        $this->actingAs($reviewer)
            ->get(Workspace::getUrl(['workspace' => 'construccion-metas-resultado']))
            ->assertOk()
            ->assertSee('Meta visible del revisor')
            ->assertDontSee('Meta de otro revisor');

        $this->actingAs($reviewer)
            ->get(Workspace::getUrl(['workspace' => 'construccion-meta-resultado', 'record' => $assignedTask->id]))
            ->assertOk();

        $this->actingAs($reviewer)
            ->get(Workspace::getUrl(['workspace' => 'construccion-meta-resultado', 'record' => $hiddenTask->id]))
            ->assertForbidden();
    }

    public function test_puede_usar_avance_gestion_como_resultado(): void
    {
        $this->withoutVite();

        $validator = User::factory()->create(['role' => UserRole::OdsValidator]);
        $meta = MetaResultado::factory()->create();
        $task = MetaResultadoConstruccion::query()->create([
            'meta_resultado_id' => $meta->id,
            'assigned_to' => $validator->id,
            'management_progress_pct' => 35,
        ]);

        $this->actingAs($validator)
            ->patch(route('intelligence.construccion-metas-resultado.update', $task), [
                'assigned_to' => $validator->id,
                'status' => 'completed',
                'measurement_mode' => 'management_as_result',
                'baseline_value' => null,
                'current_value' => null,
                'current_value_date' => null,
                'current_value_source' => 'No hay medición directa disponible',
                'methodology_notes' => 'Se adopta el avance de gestión como medición de resultado.',
            ])
            ->assertRedirect();

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertSame('management_as_result', $task->measurement_mode);
        $this->assertSame(0.0, (float) $task->result_progress_pct);
        $this->assertSame($validator->id, $task->reviewed_by);
    }
}
