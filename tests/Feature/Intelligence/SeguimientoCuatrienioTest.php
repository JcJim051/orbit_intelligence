<?php

namespace Tests\Feature\Intelligence;

use App\Enums\UserRole;
use App\Filament\Pages\Workspace;
use App\Http\Controllers\Intelligence\ReporteSectorial\Seguimiento4mController;
use App\Models\IndicadorProducto;
use App\Models\MetaProducto;
use App\Models\PlanIndicativoMeta;
use App\Models\Seguimiento4mMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeguimientoCuatrienioTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_analitica_lista_todas_las_dependencias_incluso_sin_meta_cuatrienal(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        foreach (range(1, 11) as $indice) {
            Seguimiento4mMeta::query()->create([
                'codigo_meta_producto' => '200000000'.str_pad((string) $indice, 2, '0', STR_PAD_LEFT),
                'vigencia' => 2025,
                'fila_fuente' => 1,
                'responsable' => 'Dependencia '.$indice,
                'orientacion' => 'Suma',
                'programado_fisico' => 10,
                'ejecutado_fisico' => 1,
            ]);
        }

        $response = $this->actingAs($admin)->get(Workspace::getUrl([
            'workspace' => 'cuatrienio',
            'tab' => 'analitica',
        ]));

        $response
            ->assertOk()
            ->assertSee('Paginación de dependencias')
            ->assertSee('Anterior')
            ->assertSee('Siguiente')
            ->assertSee('de 2');

        $analitica = $this->analitica(2025);

        $this->assertCount(11, $analitica['por_dependencia']);
        $this->assertTrue(collect($analitica['por_dependencia'])->every(
            fn (array $dependencia): bool => $dependencia['promedio'] === null
                && $dependencia['metas_evaluables'] === 0,
        ));
    }

    public function test_la_analitica_mide_el_acumulado_contra_la_meta_cuatrienal(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $metaSuma = $this->metaConIndicador('10000000001', 'suma', 100);
        $metaConstante = $this->metaConIndicador('10000000002', 'constante', 1);
        $metaCero = $this->metaConIndicador('10000000003', 'suma', 100);
        $metaDificil = $this->metaConIndicador('10000000004', 'suma', 100);
        $metaCumplida = $this->metaConIndicador('10000000005', 'suma', 100);
        $metaSinEstandar = $this->metaConIndicador('10000000006', 'suma', null);
        $metaDesdePlan = $this->metaConIndicador('10000000007', 'suma', null);
        $metaDecreciente = $this->metaConIndicador('10000000008', 'decreciente', 0, 10);

        foreach ([2024, 2025, 2026, 2027] as $vigencia) {
            PlanIndicativoMeta::query()->create([
                'meta_producto_id' => $metaSuma->id,
                'vigencia' => $vigencia,
                'valor_programado' => 25,
            ]);
            PlanIndicativoMeta::query()->create([
                'meta_producto_id' => $metaConstante->id,
                'vigencia' => $vigencia,
                'valor_programado' => 1,
            ]);
            PlanIndicativoMeta::query()->create([
                'meta_producto_id' => $metaDesdePlan->id,
                'vigencia' => $vigencia,
                'valor_programado' => 10,
            ]);
            PlanIndicativoMeta::query()->create([
                'meta_producto_id' => $metaDecreciente->id,
                'vigencia' => $vigencia,
                'valor_programado' => [2024 => 7, 2025 => 5, 2026 => 2, 2027 => 0][$vigencia],
            ]);
        }

        $this->fila($metaSuma, 2024, 25, 30);
        $this->fila($metaSuma, 2025, 25, 20);
        $this->fila($metaConstante, 2024, 1, 1, 'Constante');
        $this->fila($metaConstante, 2025, 1, 1, 'Constante');
        $this->fila($metaCero, 2025, 50, 0);
        $this->fila($metaDificil, 2025, 50, 20);
        $this->fila($metaCumplida, 2025, 50, 80);
        $this->fila($metaSinEstandar, 2025, 0, 0);
        $this->fila($metaDesdePlan, 2024, 10, 10);
        $this->fila($metaDesdePlan, 2025, 10, 10);
        $this->fila($metaDecreciente, 2024, 7, 7, 'Decreciente');
        $this->fila($metaDecreciente, 2025, 5, 5, 'Decreciente');

        $response = $this->actingAs($admin)->get(Workspace::getUrl([
            'workspace' => 'cuatrienio',
            'tab' => 'analitica',
        ]));

        $response
            ->assertOk()
            ->assertSee('Meta cuatrienio')
            ->assertSee('Cumplimiento al corte')
            ->assertSee($metaCumplida->codigo)
            ->assertSee($metaCumplida->nombre);

        $analitica = $this->analitica(2025);
        $metas = collect($analitica['por_meta'])->keyBy('codigo');

        $this->assertSame(2025, $analitica['vigencia']);
        $this->assertSame(8, $analitica['total']);
        $this->assertSame(1, $analitica['en_cero']);
        $this->assertSame(1, $analitica['dificiles']);
        $this->assertSame(2, $analitica['cumplidas']);
        $this->assertSame(1, $analitica['sin_programacion']);
        $this->assertSame(50.0, $analitica['promedio']);
        $this->assertSame(100.0, $metas[$metaSuma->codigo]['programado']);
        $this->assertSame(50.0, $metas[$metaSuma->codigo]['programado_al_corte']);
        $this->assertSame(50.0, $metas[$metaSuma->codigo]['ejecutado']);
        $this->assertSame(50.0, $metas[$metaSuma->codigo]['porcentaje']);
        $this->assertSame(1.0, $metas[$metaConstante->codigo]['ejecutado']);
        $this->assertSame(100.0, $metas[$metaConstante->codigo]['porcentaje']);
        $this->assertSame(40.0, $metas[$metaDesdePlan->codigo]['programado']);
        $this->assertSame(20.0, $metas[$metaDesdePlan->codigo]['programado_al_corte']);
        $this->assertSame(50.0, $metas[$metaDesdePlan->codigo]['porcentaje']);
        $this->assertSame(0.0, $metas[$metaDecreciente->codigo]['programado']);
        $this->assertSame(5.0, $metas[$metaDecreciente->codigo]['ejecutado']);
        $this->assertSame(50.0, $metas[$metaDecreciente->codigo]['porcentaje']);
        $this->assertSame(100.0, $metas[$metaDecreciente->codigo]['cumplimiento_al_corte']);
    }

    private function metaConIndicador(
        string $codigo,
        string $tipoAcumulacion,
        ?float $metaCuatrienio,
        ?float $lineaBase = null,
    ): MetaProducto
    {
        $meta = MetaProducto::factory()->create(['codigo' => $codigo]);

        IndicadorProducto::factory()->for($meta)->create([
            'codigo' => 'IP-'.$codigo,
            'tipo_acumulacion' => $tipoAcumulacion,
            'linea_base' => $lineaBase,
            'meta_cuatrienio' => $metaCuatrienio,
        ]);

        return $meta;
    }

    private function fila(
        MetaProducto $meta,
        int $vigencia,
        float $programado,
        float $ejecutado,
        string $orientacion = 'Suma',
    ): void {
        Seguimiento4mMeta::query()->create([
            'meta_producto_id' => $meta->id,
            'codigo_meta_producto' => $meta->codigo,
            'vigencia' => $vigencia,
            'fila_fuente' => 1,
            'orientacion' => $orientacion,
            'programado_fisico' => $programado,
            'ejecutado_fisico' => $ejecutado,
            'comprometido' => 0,
        ]);
    }

    /** @return array<string, mixed> */
    private function analitica(int $vigencia): array
    {
        $filas = Seguimiento4mMeta::query()
            ->with([
                'metaProducto.dependencia',
                'metaProducto.indicadorProducto',
                'metaProducto.planIndicativo',
            ])
            ->where('vigencia', '<=', $vigencia)
            ->get();
        $method = new \ReflectionMethod(Seguimiento4mController::class, 'analitica');

        return $method->invoke(app(Seguimiento4mController::class), $filas, $vigencia);
    }
}
