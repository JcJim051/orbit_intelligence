<?php

namespace Tests\Feature\InteligenciaGeografica;

use App\Enums\UserRole;
use App\Models\InteligenciaGeografica\AnalisisArea;
use App\Models\InteligenciaGeografica\Capa;
use App\Models\Suelos\ClasificacionUscs;
use App\Models\Suelos\EstudioSuelos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelAutorizacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_technical_administrator_can_manage_layers_and_soil_catalogs(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $member = User::factory()->create(['role' => UserRole::Member]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->assertTrue($admin->can('viewAny', Capa::class));
        $this->assertTrue($admin->can('create', Capa::class));
        $this->assertTrue($admin->can('export', Capa::class));
        $this->assertTrue($admin->can('import', Capa::class));
        $this->assertTrue($admin->can('viewAny', ClasificacionUscs::class));
        $this->assertTrue($admin->can('export', EstudioSuelos::class));
        $this->assertTrue($admin->can('import', EstudioSuelos::class));

        foreach ([$member, $manager] as $usuario) {
            $this->assertFalse($usuario->can('viewAny', Capa::class));
            $this->assertFalse($usuario->can('export', Capa::class));
            $this->assertFalse($usuario->can('import', Capa::class));
            $this->assertFalse($usuario->can('create', ClasificacionUscs::class));
            $this->assertFalse($usuario->can('viewAny', ClasificacionUscs::class));
            $this->assertFalse($usuario->can('export', EstudioSuelos::class));
            $this->assertFalse($usuario->can('import', EstudioSuelos::class));
        }

        $this->assertFalse($member->can('viewAny', AnalisisArea::class));
        $this->assertTrue($manager->can('viewAny', AnalisisArea::class));

        $this->actingAs($member)->get('/gestion/capas')->assertForbidden();
        $this->actingAs($member)->get('/gestion/sincronizaciones')->assertForbidden();
        $this->actingAs($member)->get('/gestion/analisis-area')->assertForbidden();
        $this->actingAs($member)->get('/gestion/visor-capas')->assertForbidden();
        $this->actingAs($member)->get(route('inteligencia.capas.geojson', ['capa' => 1]))->assertForbidden();
        $this->actingAs($member)->get('/gestion/clasificacion-uscs')->assertForbidden();
        $this->actingAs($manager)->get('/gestion/capas')->assertForbidden();
        $this->actingAs($member)->get(route('inteligencia.capas.exportar'))->assertForbidden();
        $this->actingAs($member)->get(route('inteligencia.capas.plantilla'))->assertForbidden();
        $this->actingAs($member)->post(route('inteligencia.capas.importar'))->assertForbidden();
        $this->actingAs($member)->get(route('inteligencia.catalogos.exportar', 'uscs'))->assertForbidden();
        $this->actingAs($member)->get(route('inteligencia.estudios.plantilla'))->assertForbidden();
    }
}
