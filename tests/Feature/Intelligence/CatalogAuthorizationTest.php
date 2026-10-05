<?php

namespace Tests\Feature\Intelligence;

use App\Enums\UserRole;
use App\Models\Dependencia;
use App\Models\DependenciaReglaPasiva;
use App\Models\IndicadorResultado;
use App\Models\MetaProducto;
use App\Models\MetaResultado;
use App\Models\Municipio;
use App\Models\PddEje;
use App\Models\PddLinea;
use App\Models\PddPilar;
use App\Models\PddPrograma;
use App\Models\PddSubprograma;
use App\Models\SectorMga;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    #[DataProvider('catalogs')]
    public function test_guest_is_redirected_to_login(string $routeName, string $parameter, string $model): void
    {
        $this->get(route($routeName.'.index'))->assertRedirect(route('login'));
    }

    #[DataProvider('catalogs')]
    public function test_administrator_sees_excel_actions_and_other_roles_get_a_read_only_list(string $routeName, string $parameter, string $model): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $record = $model::factory()->create();

        $this->actingAs($admin)
            ->followingRedirects()
            ->get(route($routeName.'.index'))
            ->assertOk()
            ->assertSee('Descargar todo')
            ->assertSee('Importar desde Excel')
            ->assertSee('Descargar plantilla')
            ->assertSee('Nuevo registro')
            ->assertSee('Editar');

        $this->actingAs($admin)->get(route($routeName.'.export'))->assertOk();
        $this->actingAs($admin)->get(route($routeName.'.template'))->assertOk();
        $this->actingAs($admin)->get(route($routeName.'.create'))->assertOk();
        $this->actingAs($admin)->followingRedirects()->get(route($routeName.'.edit', $record))->assertOk();

        foreach ([UserRole::Member, UserRole::Manager, UserRole::Reviewer, UserRole::ManagementSupport, UserRole::SiidManager] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->followingRedirects()
                ->get(route($routeName.'.index'))
                ->assertOk()
                ->assertDontSee('Descargar todo')
                ->assertDontSee('Importar desde Excel')
                ->assertDontSee('Descargar plantilla')
                ->assertDontSee('Nuevo registro')
                ->assertDontSee('Editar');

            $this->actingAs($user)->get(route($routeName.'.export'))->assertForbidden();
            $this->actingAs($user)->post(route($routeName.'.import'))->assertForbidden();
            $this->actingAs($user)->get(route($routeName.'.template'))->assertForbidden();
            $this->actingAs($user)->get(route($routeName.'.create'))->assertForbidden();
            $this->actingAs($user)->post(route($routeName.'.store'), [])->assertForbidden();
            $this->actingAs($user)->get(route($routeName.'.edit', $record))->assertForbidden();
            $this->actingAs($user)->patch(route($routeName.'.update', $record), [])->assertForbidden();
            $this->actingAs($user)->delete(route($routeName.'.destroy', $record))->assertForbidden();
        }
    }

    public function test_only_the_technical_administrator_passes_the_catalog_policy(): void
    {
        $dependencia = Dependencia::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertTrue($admin->can('viewAny', Dependencia::class));
        $this->assertTrue($admin->can('create', Dependencia::class));
        $this->assertTrue($admin->can('export', Dependencia::class));
        $this->assertTrue($admin->can('import', Dependencia::class));
        $this->assertTrue($admin->can('update', $dependencia));
        $this->assertTrue($admin->can('delete', $dependencia));

        foreach ([UserRole::Member, UserRole::Manager, UserRole::Reviewer, UserRole::ManagementSupport, UserRole::SiidManager] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->assertTrue($user->can('viewAny', Dependencia::class));
            $this->assertTrue($user->can('view', $dependencia));
            $this->assertFalse($user->can('create', Dependencia::class));
            $this->assertFalse($user->can('export', Dependencia::class));
            $this->assertFalse($user->can('import', Dependencia::class));
            $this->assertFalse($user->can('update', $dependencia));
            $this->assertFalse($user->can('delete', $dependencia));
        }
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: class-string}>
     */
    public static function catalogs(): array
    {
        return [
            'dependencias' => ['intelligence.dependencias', 'dependencia', Dependencia::class],
            'municipios' => ['intelligence.municipios', 'municipio', Municipio::class],
            'reglas de pasiva' => ['intelligence.reglas-pasiva', 'regla', DependenciaReglaPasiva::class],
            'pilares' => ['intelligence.pilares', 'pilar', PddPilar::class],
            'ejes' => ['intelligence.ejes', 'eje', PddEje::class],
            'líneas' => ['intelligence.lineas', 'linea', PddLinea::class],
            'programas' => ['intelligence.programas', 'programa', PddPrograma::class],
            'subprogramas' => ['intelligence.subprogramas', 'subprograma', PddSubprograma::class],
            'sectores mga' => ['intelligence.sectores-mga', 'sector', SectorMga::class],
            'metas producto' => ['intelligence.metas-producto', 'metaProducto', MetaProducto::class],
            'indicadores de resultado' => ['intelligence.indicadores-resultado', 'indicador', IndicadorResultado::class],
            'metas resultado' => ['intelligence.metas-resultado', 'metaResultado', MetaResultado::class],
        ];
    }
}
