<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\IndicadorPublicos\Pages\ManageIndicadorPublicos;
use App\Filament\Resources\MicrositioIndicadorResource;
use App\Filament\Resources\MicrositioIndicadorResource\Pages\ListMicrositioIndicadores;
use App\Filament\Resources\MicrositioTemaResource;
use App\Models\Dashboard;
use App\Models\MicrositioIndicador;
use App\Models\MicrositioTema;
use App\Models\User;
use App\Services\Indicators\IndicatorSeriesManager;
use App\Services\Micrositio\HojaMicrositio;
use App\Services\Micrositio\PublicadorMicrositio;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class IndicadorPublicoCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('management'));
    }

    public function test_admin_creates_edits_and_deletes_an_indicator_from_the_indicator_module(): void
    {
        $tema = MicrositioTema::factory()->create(['nombre' => 'Gestión pública']);
        $dashboard = Dashboard::factory()->create([
            'name' => 'Tablero institucional',
            'superset_embedded_uuid' => '4f69b6c1-7c67-4b94-bd9a-bf90e46b5adb',
        ]);

        $this->get('/gestion/indicadores-publicos')->assertOk();

        Livewire::test(ManageIndicadorPublicos::class)
            ->assertActionExists('plantilla')
            ->assertActionExists('exportar')
            ->assertActionExists('importar')
            ->callAction('create', data: [
                'codigo' => 'IND-001',
                'nombre' => 'Indicador institucional',
                'micrositio_tema_id' => $tema->id,
                'dashboard_id' => $dashboard->id,
                'descripcion' => 'Descripción pública del indicador.',
                'conjunto_datos' => null,
                'orden' => 1,
                'es_ejemplo' => false,
            ])
            ->assertHasNoActionErrors();

        $indicador = MicrositioIndicador::query()->where('codigo', 'IND-001')->firstOrFail();
        $this->assertFalse($indicador->es_publico);
        $this->assertSame($dashboard->id, $indicador->dashboard_id);
        $this->assertSame($dashboard->superset_embedded_uuid, $indicador->dashboard_uuid);
        $this->get('/gestion/indicadores-publicos/'.$indicador->id.'/serie')
            ->assertOk()
            ->assertSee('Constructor de serie de datos');

        Livewire::test(ManageIndicadorPublicos::class)
            ->callTableAction('edit', $indicador, data: [
                'codigo' => 'IND-001',
                'nombre' => 'Indicador institucional actualizado',
                'micrositio_tema_id' => $tema->id,
                'dashboard_id' => $dashboard->id,
                'descripcion' => 'Descripción actualizada.',
                'conjunto_datos' => null,
                'orden' => 2,
                'es_ejemplo' => false,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('micrositio_indicadores', [
            'id' => $indicador->id,
            'nombre' => 'Indicador institucional actualizado',
            'es_publico' => false,
        ]);

        Livewire::test(ManageIndicadorPublicos::class)
            ->callTableAction('delete', $indicador->fresh())
            ->assertHasNoActionErrors();

        $this->assertDatabaseMissing('micrositio_indicadores', ['id' => $indicador->id]);
    }

    public function test_microsite_screen_only_controls_visibility(): void
    {
        $indicador = MicrositioIndicador::factory()->create();

        Livewire::test(ListMicrositioIndicadores::class)
            ->assertTableActionDoesNotExist('edit', null, $indicador)
            ->assertTableActionDoesNotExist('delete', null, $indicador);

        $this->assertFalse(MicrositioIndicadorResource::puedeCrear());
    }

    public function test_thematic_axes_are_presented_inside_the_indicators_module(): void
    {
        $this->assertSame('Indicadores', MicrositioTemaResource::getNavigationGroup());
        $this->assertSame('Ejes temáticos', MicrositioTemaResource::getNavigationLabel());
        $this->get('/gestion/micrositio-temas')->assertOk()->assertSee('Ejes temáticos');
    }

    public function test_bulk_load_relates_the_dashboard_and_preserves_visibility(): void
    {
        $tema = MicrositioTema::factory()->create(['slug' => 'gestion-publica']);
        $dashboard = Dashboard::factory()->create([
            'superset_embedded_uuid' => '4f69b6c1-7c67-4b94-bd9a-bf90e46b5adb',
        ]);
        MicrositioIndicador::factory()->create([
            'codigo' => 'IND-001',
            'es_publico' => true,
        ]);
        $ruta = storage_path('framework/testing/carga-indicadores.xlsx');
        $writer = new Writer;
        $writer->openToFile($ruta);
        $writer->addRow(Row::fromValues([
            'id', 'codigo', 'nombre', 'tema_slug', 'dashboard_uuid', 'descripcion', 'es_ejemplo', 'conjunto_datos', 'orden',
        ]));
        $writer->addRow(Row::fromValues([
            '', 'IND-001', 'Indicador actualizado en bloque', $tema->slug, $dashboard->superset_embedded_uuid,
            'Descripción importada.', 0, '', 1,
        ]));
        $writer->close();

        $this->assertSame(1, app(HojaMicrositio::class)->importar('indicadores', $ruta));

        $indicador = MicrositioIndicador::query()->where('codigo', 'IND-001')->firstOrFail();
        $this->assertTrue($indicador->es_publico);
        $this->assertSame($dashboard->id, $indicador->dashboard_id);
        $this->assertSame('Indicador actualizado en bloque', $indicador->nombre);
    }

    public function test_series_constructor_defines_columns_and_loads_a_validated_version(): void
    {
        $indicator = MicrositioIndicador::factory()->create(['codigo' => 'SER-001']);
        $manager = app(IndicatorSeriesManager::class);
        /** @var User $user */
        $user = auth()->user();

        $schemaVersion = $manager->saveSchema($indicator, [
            ['key' => 'periodo', 'label' => 'Periodo', 'type' => 'date', 'required' => true],
            ['key' => 'valor', 'label' => 'Valor', 'type' => 'decimal', 'required' => true],
            ['key' => 'municipio', 'label' => 'Municipio', 'type' => 'text', 'required' => false],
        ], $user);

        $this->assertSame(1, $schemaVersion->version);
        $this->assertNotNull($indicator->fresh()->tabular_data_source_id);

        $file = UploadedFile::fake()->createWithContent(
            'serie.csv',
            "periodo,valor,municipio\n2026-10-31,25.5,Villavicencio\n",
        );
        $dataVersion = $manager->import($indicator->fresh(), $file, $user);

        $this->assertSame(2, $dataVersion->version);
        $this->assertSame(1, $dataVersion->row_count);
        $this->assertSame(25.5, $dataVersion->records[0]['valor']);
        $this->assertSame('2026-10-31', $dataVersion->records[0]['periodo']);
    }

    public function test_public_csv_remains_pinned_to_the_published_series_version(): void
    {
        $indicator = MicrositioIndicador::factory()->create(['codigo' => 'SER-002', 'es_publico' => true]);
        $manager = app(IndicatorSeriesManager::class);
        /** @var User $user */
        $user = auth()->user();
        $manager->saveSchema($indicator, [
            ['key' => 'periodo', 'label' => 'Periodo', 'type' => 'date', 'required' => true],
            ['key' => 'valor', 'label' => 'Valor', 'type' => 'decimal', 'required' => true],
        ], $user);
        $manager->import($indicator->fresh(), UploadedFile::fake()->createWithContent('serie.csv', "periodo,valor\n2026-01-31,10\n"), $user);
        app(PublicadorMicrositio::class)->publicar('indicadores');

        $manager->import($indicator->fresh(), UploadedFile::fake()->createWithContent('serie.csv', "periodo,valor\n2026-02-28,99\n"), $user);

        $response = $this->get(route('micrositio.indicadores.csv', 'SER-002'))->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('10', $content);
        $this->assertStringNotContainsString('99', $content);
    }
}
