<?php

namespace Tests\Unit\Services\Intelligence;

use App\Enums\TipoReglaPasiva;
use App\Models\Dependencia;
use App\Models\DependenciaReglaPasiva;
use App\Services\Intelligence\ResolverDependenciaPasiva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolverDependenciaPasivaTest extends TestCase
{
    use RefreshDatabase;

    public function test_bpin_outranks_prefix_sector_and_budget_unit(): void
    {
        $hacienda = Dependencia::factory()->create();
        $salud = Dependencia::factory()->create();
        $aim = Dependencia::factory()->create();
        $vivienda = Dependencia::factory()->create();

        $this->regla($hacienda, TipoReglaPasiva::UnidadPct, '0301');
        $this->regla($salud, TipoReglaPasiva::SectorMga, '19');
        $this->regla($aim, TipoReglaPasiva::PrefijoRubro, '0301 - 2.3.19');
        $bpin = $this->regla($vivienda, TipoReglaPasiva::Bpin, '2023005500070');

        $this->assertSame(100, DependenciaReglaPasiva::query()->where('valor', '0301')->value('prioridad'));
        $this->assertSame(200, DependenciaReglaPasiva::query()->where('valor', '19')->value('prioridad'));
        $this->assertSame(300, DependenciaReglaPasiva::query()->where('valor', '0301 - 2.3.19')->value('prioridad'));
        $this->assertSame(400, $bpin->fresh()->prioridad);

        $this->assertSame($vivienda->id, $this->resolver()->resolver(
            '0301 - 2.3.19.1903.0300',
            'El proyecto 2023005500070 quedó reportado en la matriz',
        ));
    }

    public function test_more_specific_rule_wins_when_a_higher_one_does_not_match(): void
    {
        $hacienda = Dependencia::factory()->create();
        $salud = Dependencia::factory()->create();
        $aim = Dependencia::factory()->create();

        $this->regla($hacienda, TipoReglaPasiva::UnidadPct, '030302');
        $this->regla($salud, TipoReglaPasiva::SectorMga, '19');
        $this->regla($aim, TipoReglaPasiva::PrefijoRubro, '030302 - 2.3.19.1903');

        $this->assertSame($aim->id, $this->resolver()->resolver(
            '030302 - 2.3.19.1903.0300',
            'SUBPROGRAMA: Intersubsectorial Salud',
        ));

        $this->assertSame($salud->id, $this->resolver()->resolver(
            '030302 - 2.3.19.1906.0300',
            'Sin el prefijo del rubro 1903',
        ));
    }

    public function test_longer_prefix_wins_over_a_shorter_prefix_with_the_same_priority(): void
    {
        $general = Dependencia::factory()->create();
        $especifica = Dependencia::factory()->create();

        DependenciaReglaPasiva::factory()->create([
            'dependencia_id' => $general->id,
            'tipo_regla' => TipoReglaPasiva::PrefijoRubro,
            'valor' => '0301 - 2.3.19',
            'prioridad' => 300,
        ]);
        DependenciaReglaPasiva::factory()->create([
            'dependencia_id' => $especifica->id,
            'tipo_regla' => TipoReglaPasiva::PrefijoRubro,
            'valor' => '0301 - 2.3.19.1903',
            'prioridad' => 300,
        ]);

        $this->assertSame($especifica->id, $this->resolver()->resolver(
            '0301 - 2.3.19.1903.0300',
            'Sin BPIN',
        ));
    }

    public function test_bpin_rule_wins_even_when_another_rule_has_higher_manual_priority(): void
    {
        $unidad = Dependencia::factory()->create();
        $bpin = Dependencia::factory()->create();

        DependenciaReglaPasiva::factory()->create([
            'dependencia_id' => $unidad->id,
            'tipo_regla' => TipoReglaPasiva::UnidadPct,
            'valor' => '0301',
            'prioridad' => 900,
        ]);
        DependenciaReglaPasiva::factory()->create([
            'dependencia_id' => $bpin->id,
            'tipo_regla' => TipoReglaPasiva::Bpin,
            'valor' => '2023005500070',
            'prioridad' => 400,
        ]);

        $this->assertSame($bpin->id, $this->resolver()->resolver(
            '0301 - 2.3.19.1903.0300',
            'BPIN 2023005500070',
        ));
    }

    public function test_higher_priority_bpin_rule_wins_between_bpin_matches(): void
    {
        $general = Dependencia::factory()->create();
        $confirmada = Dependencia::factory()->create();

        DependenciaReglaPasiva::factory()->create([
            'dependencia_id' => $general->id,
            'tipo_regla' => TipoReglaPasiva::Bpin,
            'valor' => '2023005500070',
            'prioridad' => 400,
        ]);
        DependenciaReglaPasiva::factory()->create([
            'dependencia_id' => $confirmada->id,
            'tipo_regla' => TipoReglaPasiva::Bpin,
            'valor' => '2023005500070',
            'prioridad' => 450,
        ]);

        $this->assertSame($confirmada->id, $this->resolver()->resolver(
            '0301 - 2.3.19.1903.0300',
            'BPIN 2023005500070',
        ));
    }

    public function test_budget_unit_sector_and_bpin_do_not_match_partial_values(): void
    {
        $dependencia = Dependencia::factory()->create();
        $this->regla($dependencia, TipoReglaPasiva::UnidadPct, '0303');
        $this->regla($dependencia, TipoReglaPasiva::SectorMga, '1');
        $this->regla($dependencia, TipoReglaPasiva::Bpin, '2023005500070');
        DependenciaReglaPasiva::factory()->create([
            'dependencia_id' => $dependencia->id,
            'tipo_regla' => TipoReglaPasiva::PrefijoRubro,
            'valor' => '0301 - 2.3.1',
            'prioridad' => 300,
        ]);

        $resolver = $this->resolver();

        $this->assertNull($resolver->resolver('030301 - 2.3.19.1903.0300', 'Otra unidad'));
        $this->assertNull($resolver->resolver('0301 - 2.1.1.01.01.001.01 - 20', 'Sueldo básico'));
        $this->assertNull($resolver->resolver('0301 - 2.3.19.1903.0300', 'Código 20230055000701'));
        $this->assertNull($resolver->resolver('0301 - 2.3.19.1903.0300', 'Sin identificador'));
    }

    public function test_inactive_rules_closed_fiscal_years_and_deleted_dependencies_are_ignored(): void
    {
        $activa = Dependencia::factory()->create();
        $inactiva = Dependencia::factory()->create();
        $borrada = Dependencia::factory()->create();
        $borrada->delete();

        DependenciaReglaPasiva::factory()->create([
            'dependencia_id' => $inactiva->id,
            'tipo_regla' => TipoReglaPasiva::UnidadPct,
            'valor' => '0301',
            'activo' => false,
        ]);
        DependenciaReglaPasiva::factory()->create([
            'dependencia_id' => $borrada->id,
            'tipo_regla' => TipoReglaPasiva::Bpin,
            'valor' => '2023005500070',
            'activo' => true,
        ]);
        DependenciaReglaPasiva::factory()->create([
            'dependencia_id' => $activa->id,
            'tipo_regla' => TipoReglaPasiva::UnidadPct,
            'valor' => '0304',
            'vigencia_desde' => 2026,
            'vigencia_hasta' => 2026,
        ]);
        $this->regla($activa, TipoReglaPasiva::UnidadPct, '0305');

        $resolver = $this->resolver();

        $this->assertNull($resolver->resolver('0301 - 2.3.19.1903.0300', 'BPIN 2023005500070'));
        $this->assertNull($resolver->resolver('0304 - 2.3.45.4501.1000', 'Sin vigencia', 2025));
        $this->assertSame($activa->id, $resolver->resolver('0304 - 2.3.45.4501.1000', 'Vigencia 2026', 2026));
        $this->assertNull($resolver->resolver('0304 - 2.3.45.4501.1000', 'Sin año informado'));
        $this->assertSame($activa->id, $resolver->resolver('0305 - 2.3.22.2202.0700', 'Educación'));
    }

    private function regla(Dependencia $dependencia, TipoReglaPasiva $tipo, string $valor): DependenciaReglaPasiva
    {
        return DependenciaReglaPasiva::query()->create([
            'dependencia_id' => $dependencia->id,
            'tipo_regla' => $tipo,
            'valor' => $valor,
            'activo' => true,
        ]);
    }

    private function resolver(): ResolverDependenciaPasiva
    {
        return new ResolverDependenciaPasiva;
    }
}
