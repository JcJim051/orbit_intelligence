<?php

namespace Tests\Feature\InteligenciaGeografica;

use App\Enums\ServidoDesde;
use App\Enums\UserRole;
use App\Filament\Resources\AnalisisAreaResource;
use App\Models\InteligenciaGeografica\AnalisisResultado;
use App\Models\InteligenciaGeografica\Capa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\RequiresPostgis;
use Tests\TestCase;

#[Group('postgis')]
class AtribucionEnPanelTest extends TestCase
{
    use RefreshDatabase, RequiresPostgis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRequiresPostgis();
        $this->withoutVite();
    }

    public function test_analysis_view_shows_the_source_attribution_and_pending_layers_without_data(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $cita = 'Agencia Nacional de Minería. Títulos mineros. https://ejemplo.test/anm. Licencia de prueba.';
        $resultado = AnalisisResultado::factory()->create([
            'cita_fuente' => $cita,
            'url_fuente' => 'https://ejemplo.test/anm',
            'licencia' => 'Licencia de prueba',
            'servido_desde' => ServidoDesde::CopiaPorFallaFuente,
            'obsoleto' => true,
            'conteo' => 2,
        ]);
        Capa::factory()->create([
            'codigo' => 'CAPA-PENDIENTE',
            'nombre' => 'Capa sin conector',
            'activa' => true,
        ]);

        $this->actingAs($admin)
            ->get(AnalisisAreaResource::getUrl('view', ['record' => $resultado->analisis]))
            ->assertOk()
            ->assertSee($cita)
            ->assertSee('https://ejemplo.test/anm')
            ->assertSee('Licencia de prueba')
            ->assertSee('Copia local porque la fuente falló')
            ->assertSee('Posiblemente desactualizada')
            ->assertSee('CAPA-PENDIENTE — Capa sin conector')
            ->assertSee('Pendiente de conector');

        $this->assertSame(1, $resultado->analisis->resultados()->count());
    }

    public function test_administrator_sees_excel_actions_on_the_layer_registry(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $capa = Capa::factory()->create([
            'codigo' => 'CAPA-LISTA',
            'nombre' => 'Capa visible',
        ]);

        $this->actingAs($admin)
            ->get('/gestion/capas')
            ->assertOk()
            ->assertSee($capa->etiqueta())
            ->assertSee('Descargar todo')
            ->assertSee('Importar desde Excel')
            ->assertSee('Descargar plantilla')
            ->assertSee('Nueva capa');
    }
}
