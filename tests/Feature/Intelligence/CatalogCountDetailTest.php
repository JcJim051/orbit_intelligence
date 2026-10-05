<?php

namespace Tests\Feature\Intelligence;

use App\Enums\TipoReglaPasiva;
use App\Enums\UserRole;
use App\Models\Dependencia;
use App\Models\DependenciaReglaPasiva;
use App\Models\IndicadorResultado;
use App\Models\MetaProducto;
use App\Models\MetaResultado;
use App\Models\PddEje;
use App\Models\PddLinea;
use App\Models\PddPilar;
use App\Models\PddPrograma;
use App\Models\PddSubprograma;
use App\Models\SectorMga;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogCountDetailTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, Model> */
    private array $plan = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Plan pequeño con filas eliminadas lógicamente que NO deben contarse.
     *
     * @return array<string, Model>
     */
    private function buildPlan(): array
    {
        $pilar = PddPilar::factory()->create(['codigo' => '10000000000', 'numeral' => '1', 'nombre' => 'SEGURIDAD TOTAL']);
        $eje = PddEje::factory()->create(['codigo' => '11000000000', 'numeral' => '1.1', 'nombre' => 'EJE ESTRATÉGICO CIUDADANÍA SEGURA', 'pilar_id' => $pilar->id]);
        PddEje::factory()->create(['codigo' => '12000000000', 'numeral' => '1.2', 'nombre' => 'EJE ESTRATÉGICO SIN LÍNEAS', 'pilar_id' => $pilar->id]);
        $linea = PddLinea::factory()->create(['codigo' => '11010000000', 'numeral' => '1.1.1', 'nombre' => 'LÍNEA ESTRATÉGICA INSTITUCIONES', 'eje_id' => $eje->id]);
        PddLinea::factory()->create(['codigo' => '11020000000', 'numeral' => '1.1.2', 'eje_id' => $eje->id])->delete();
        $programa = PddPrograma::factory()->create(['codigo' => '11011000000', 'numeral' => '1.1.1.1', 'nombre' => 'PROGRAMA CAPACIDADES', 'linea_id' => $linea->id]);
        $subA = PddSubprograma::factory()->create(['codigo' => '11011010000', 'numeral' => '1.1.1.1.1', 'nombre' => 'Subprograma A', 'programa_id' => $programa->id]);
        $subB = PddSubprograma::factory()->create(['codigo' => '11011020000', 'numeral' => '1.1.1.1.2', 'nombre' => 'Subprograma B', 'programa_id' => $programa->id]);
        $subX = PddSubprograma::factory()->create(['codigo' => '11011090000', 'numeral' => '1.1.1.1.9', 'nombre' => 'Subprograma eliminado', 'programa_id' => $programa->id]);
        $sector = SectorMga::factory()->create(['codigo' => '04', 'nombre' => 'Información Estadística']);
        $dependencia = Dependencia::factory()->create(['codigo' => 'SEC. TIC', 'nombre' => 'Secretaría TIC']);
        $indicador = IndicadorResultado::factory()->create(['nombre' => 'Estrategias ejecutadas', 'unidad_medida' => 'Número']);

        $mr1 = MetaResultado::factory()->create(['codigo_provisional' => 'MR-001', 'descripcion' => 'Reducir la brecha', 'programa_id' => $programa->id, 'subprograma_id' => $subA->id, 'indicador_resultado_id' => $indicador->id]);
        $mr2 = MetaResultado::factory()->create(['codigo_provisional' => 'MR-002', 'descripcion' => 'Meta del programa', 'programa_id' => $programa->id, 'subprograma_id' => null, 'indicador_resultado_id' => $indicador->id]);
        MetaResultado::factory()->create(['codigo_provisional' => 'MR-009', 'programa_id' => $programa->id, 'indicador_resultado_id' => $indicador->id])->delete();

        $a1 = MetaProducto::factory()->create(['codigo' => '11011010001', 'nombre' => 'Meta A1', 'subprograma_id' => $subA->id, 'sector_mga_id' => $sector->id, 'meta_resultado_id' => $mr1->id, 'dependencia_id' => $dependencia->id]);
        MetaProducto::factory()->create(['codigo' => '11011010002', 'nombre' => 'Meta A2', 'subprograma_id' => $subA->id, 'sector_mga_id' => $sector->id, 'meta_resultado_id' => null]);
        MetaProducto::factory()->create(['codigo' => '11011020001', 'nombre' => 'Meta B1', 'subprograma_id' => $subB->id, 'sector_mga_id' => $sector->id, 'meta_resultado_id' => $mr1->id, 'dependencia_id' => $dependencia->id]);
        MetaProducto::factory()->create(['codigo' => '11011010009', 'subprograma_id' => $subA->id, 'sector_mga_id' => $sector->id, 'meta_resultado_id' => $mr1->id])->delete();
        // Meta vigente cuyo subprograma está eliminado: cuenta para el sector, no para el programa.
        MetaProducto::factory()->create(['codigo' => '11011090001', 'nombre' => 'Meta X1', 'subprograma_id' => $subX->id, 'sector_mga_id' => $sector->id, 'meta_resultado_id' => $mr2->id]);
        $subX->delete();

        return $this->plan = compact('pilar', 'eje', 'linea', 'programa', 'subA', 'subB', 'sector', 'dependencia', 'indicador', 'mr1', 'mr2', 'a1');
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: int, 5: class-string<Model>}>
     */
    public static function countDetails(): array
    {
        return [
            'pilares → ejes' => ['pilares', 'pilar', 'pilar', 'ejes', 2, PddPilar::class],
            'ejes → líneas' => ['ejes', 'eje', 'eje', 'lineas', 1, PddEje::class],
            'líneas → programas' => ['lineas', 'linea', 'linea', 'programas', 1, PddLinea::class],
            'programas → subprogramas' => ['programas', 'programa', 'programa', 'subprogramas', 2, PddPrograma::class],
            'programas → metas resultado' => ['programas', 'programa', 'programa', 'metasResultado', 2, PddPrograma::class],
            'programas → metas producto' => ['programas', 'programa', 'programa', 'metasProducto', 3, PddPrograma::class],
            'subprogramas → metas producto' => ['subprogramas', 'subprograma', 'subA', 'metasProducto', 2, PddSubprograma::class],
            'subprogramas → metas resultado' => ['subprogramas', 'subprograma', 'subA', 'metasResultado', 1, PddSubprograma::class],
            'sectores → metas producto' => ['sectores-mga', 'sector', 'sector', 'metasProducto', 4, SectorMga::class],
            'metas resultado → metas producto' => ['metas-resultado', 'metaResultado', 'mr1', 'metasProducto', 2, MetaResultado::class],
            'indicadores → metas resultado' => ['indicadores-resultado', 'indicador', 'indicador', 'metasResultado', 2, IndicadorResultado::class],
            'dependencias → metas producto' => ['dependencias', 'dependencia', 'dependencia', 'metasProducto', 2, Dependencia::class],
        ];
    }

    #[DataProvider('countDetails')]
    public function test_detail_total_matches_the_listed_count_and_only_non_zero_counts_are_clickable(string $uri, string $parameter, string $key, string $relation, int $expected, string $model): void
    {
        $this->buildPlan();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $record = $this->plan[$key];
        $count = $model::query()->withCount($relation)->findOrFail($record->getKey())->{Str::snake($relation).'_count'};
        $this->assertSame($expected, $count);

        $response = $this->actingAs($admin)
            ->getJson(route('intelligence.'.$uri.'.detail', [$parameter => $record, 'relacion' => $relation]))
            ->assertOk()
            ->assertJsonPath('relacion', $relation)
            ->assertJsonPath('total', $count)
            ->assertJsonPath('resumen.0.valor', $count);

        // En los árboles del plan cada hijo es un grupo; en el resto, cada registro contado es un ítem.
        $tree = in_array($uri, ['pilares', 'ejes', 'lineas'], true);
        $listed = $tree
            ? count($response->json('grupos'))
            : collect($response->json('grupos'))->sum(fn (array $grupo): int => count($grupo['items']));
        $this->assertSame($count, $listed);

        // Registro vacío del mismo catálogo: su conteo queda como texto, sin botón.
        $empty = $model::factory()->create();
        $this->assertSame(0, $model::query()->withCount($relation)->findOrFail($empty->getKey())->{Str::snake($relation).'_count'});

        $this->actingAs($admin)
            ->followingRedirects()
            ->get(route('intelligence.'.$uri.'.index'))
            ->assertOk()
            ->assertSee('data-count-detail-url="'.route('intelligence.'.$uri.'.detail', [$parameter => $record, 'relacion' => $relation]).'"', false)
            ->assertDontSee(route('intelligence.'.$uri.'.detail', [$parameter => $empty, 'relacion' => $relation]), false)
            ->assertSee('id="count-detail-dialog"', false);
    }

    #[DataProvider('countDetails')]
    public function test_detail_requires_authentication_is_readable_by_every_role_and_rejects_unknown_relations(string $uri, string $parameter, string $key, string $relation, int $expected, string $model): void
    {
        $this->buildPlan();
        $record = $this->plan[$key];
        $url = route('intelligence.'.$uri.'.detail', [$parameter => $record, 'relacion' => $relation]);

        $this->get($url)->assertRedirect(route('login'));
        $this->getJson($url)->assertUnauthorized();

        foreach ([UserRole::Member, UserRole::Reviewer, UserRole::SiidManager] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->getJson($url)->assertOk()->assertJsonPath('total', $expected);
        }

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)
            ->getJson(route('intelligence.'.$uri.'.detail', [$parameter => $record, 'relacion' => 'noExiste']))
            ->assertNotFound();

        $record->delete();
        $this->actingAs($admin)->getJson($url)->assertNotFound();
    }

    public function test_sector_detail_groups_product_goals_by_subprogram_with_their_result_goal(): void
    {
        $this->buildPlan();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->getJson(route('intelligence.sectores-mga.detail', $this->plan['sector']))
            ->assertOk()
            ->assertJsonPath('registro.codigo', '04')
            ->assertJsonPath('registro.nombre', 'Información Estadística')
            ->assertJsonPath('resumen.1.valor', 3)
            ->assertJsonPath('resumen.1.label', 'subprogramas')
            ->assertJsonPath('resumen.2.valor', 2)
            ->assertJsonPath('resumen.2.label', 'metas resultado distintas')
            ->assertJsonCount(3, 'grupos')
            ->assertJsonPath('grupos.0.titulo', '1.1.1.1.1 — Subprograma A')
            ->assertJsonPath('grupos.0.nota', '2 metas producto')
            ->assertJsonPath('grupos.0.items.0.codigo', '11011010001')
            ->assertJsonPath('grupos.0.items.0.nombre', 'Meta A1')
            ->assertJsonPath('grupos.0.items.0.extra.0.valor', 'MR-001 — Reducir la brecha')
            ->assertJsonPath('grupos.0.items.1.extra.0.valor', 'Sin meta de resultado')
            ->assertJsonPath('grupos.1.titulo', '1.1.1.1.2 — Subprograma B')
            ->assertJsonPath('grupos.2.titulo', '1.1.1.1.9 — Subprograma eliminado (eliminado)');
    }

    public function test_pillar_detail_groups_lines_under_each_axis(): void
    {
        $this->buildPlan();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->getJson(route('intelligence.pilares.detail', $this->plan['pilar']))
            ->assertOk()
            ->assertJsonPath('registro.codigo', 'Pilar 1')
            ->assertJsonPath('registro.nombre', 'SEGURIDAD TOTAL')
            ->assertJsonPath('resumen.1.valor', 1)
            ->assertJsonPath('resumen.1.label', 'línea')
            ->assertJsonPath('grupos.0.titulo', '1.1 — EJE ESTRATÉGICO CIUDADANÍA SEGURA')
            ->assertJsonPath('grupos.0.nota', '1 línea')
            ->assertJsonPath('grupos.0.items.0.codigo', '1.1.1')
            ->assertJsonPath('grupos.0.items.0.nombre', 'LÍNEA ESTRATÉGICA INSTITUCIONES')
            ->assertJsonPath('grupos.0.items.0.extra.1.valor', '1 programa')
            ->assertJsonPath('grupos.1.titulo', '1.2 — EJE ESTRATÉGICO SIN LÍNEAS')
            ->assertJsonPath('grupos.1.items', [])
            ->assertJsonPath('grupos.1.vacio', 'Sin líneas.');
    }

    public function test_program_result_goals_start_with_program_level_goals_then_each_subprogram(): void
    {
        $this->buildPlan();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->getJson(route('intelligence.programas.detail', [$this->plan['programa'], 'relacion' => 'metasResultado']))
            ->assertOk()
            ->assertJsonPath('resumen.2.valor', 3)
            ->assertJsonPath('grupos.0.titulo', 'A nivel de programa (sin subprograma)')
            ->assertJsonPath('grupos.0.items.0.codigo', 'MR-002')
            ->assertJsonPath('grupos.1.titulo', '1.1.1.1.1 — Subprograma A')
            ->assertJsonPath('grupos.1.items.0.codigo', 'MR-001')
            ->assertJsonPath('grupos.1.items.0.nombre', 'Reducir la brecha')
            ->assertJsonPath('grupos.1.items.0.extra.0.valor', 'Estrategias ejecutadas (Número)')
            ->assertJsonPath('grupos.1.items.0.extra.1.label', 'Metas producto vinculadas')
            ->assertJsonPath('grupos.1.items.0.extra.1.valor', '2');
    }

    public function test_result_goal_shows_linked_product_goals_by_code_and_name_in_list_and_edit_form(): void
    {
        $this->buildPlan();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $single = MetaResultado::factory()->create(['codigo_provisional' => 'MR-003']);
        MetaProducto::factory()->create(['codigo' => '11011010005', 'nombre' => 'Meta única', 'subprograma_id' => $this->plan['subA']->id, 'meta_resultado_id' => $single->id]);

        $this->actingAs($admin)
            ->followingRedirects()
            ->get(route('intelligence.metas-resultado.index'))
            ->assertOk()
            ->assertSee('>11011010005 — Meta única</button>', false)
            ->assertSee('>2 metas producto</button>', false)
            ->assertSee('11011010001 — Meta A1', false);

        $this->actingAs($admin)
            ->get(route('intelligence.metas-resultado.edit', $single))
            ->assertOk()
            ->assertSee('Vínculos del registro')
            ->assertSee('data-count-detail-url="'.route('intelligence.metas-resultado.detail', [$single, 'relacion' => 'metasProducto']).'"', false)
            ->assertSee('>11011010005 — Meta única</button>', false)
            ->assertSee('id="count-detail-dialog"', false);

        $this->actingAs($admin)
            ->getJson(route('intelligence.metas-resultado.detail', [$this->plan['mr1'], 'relacion' => 'metasProducto']))
            ->assertOk()
            ->assertJsonPath('registro.codigo', 'MR-001')
            ->assertJsonPath('grupos.0.titulo', '1.1.1.1.1 — Subprograma A')
            ->assertJsonPath('grupos.0.items.0.extra.0.valor', '04 — Información Estadística')
            ->assertJsonPath('grupos.0.items.0.extra.1.valor', 'SEC. TIC — Secretaría TIC');
    }

    public function test_parent_columns_show_reference_and_name_instead_of_a_bare_code(): void
    {
        $this->buildPlan();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->followingRedirects()->get(route('intelligence.ejes.index'))->assertOk()->assertSee('Pilar 1 — SEGURIDAD TOTAL');
        $this->actingAs($admin)->followingRedirects()->get(route('intelligence.lineas.index'))->assertOk()->assertSee('1.1 — EJE ESTRATÉGICO CIUDADANÍA SEGURA');
        $this->actingAs($admin)->followingRedirects()->get(route('intelligence.programas.index'))->assertOk()->assertSee('1.1.1 — LÍNEA ESTRATÉGICA INSTITUCIONES');
        $this->actingAs($admin)->followingRedirects()->get(route('intelligence.subprogramas.index'))->assertOk()->assertSee('1.1.1.1 — PROGRAMA CAPACIDADES');
        $this->actingAs($admin)->followingRedirects()->get(route('intelligence.metas-producto.index'))->assertOk()->assertSee('1.1.1.1.1 — Subprograma A');
        $this->actingAs($admin)->followingRedirects()->get(route('intelligence.metas-resultado.index'))->assertOk()->assertSee('1.1.1.1 — PROGRAMA CAPACIDADES');

        DependenciaReglaPasiva::factory()->create(['tipo_regla' => TipoReglaPasiva::SectorMga, 'valor' => '04', 'prioridad' => null]);
        $this->actingAs($admin)->followingRedirects()->get(route('intelligence.reglas-pasiva.index'))->assertOk()->assertSee('04 — Información Estadística');
    }
}
