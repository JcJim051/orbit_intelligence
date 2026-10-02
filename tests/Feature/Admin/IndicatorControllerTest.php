<?php

namespace Tests\Feature\Admin;

use App\Enums\IndicatorStatus;
use App\Enums\UserRole;
use App\Models\Indicator;
use App\Models\TabularDataSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IndicatorControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_creates_submits_and_publishes_indicator_with_official_pdf_and_data_series(): void
    {
        Storage::fake('local');
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->actingAs($manager)->post(route('admin.indicators.store'), [
            'name' => 'Evaluaciones Agropecuarias Municipales',
            'slug' => 'evaluaciones-agropecuarias-municipales',
            'summary' => 'Serie oficial para consulta pública.',
            'sector' => 'Agricultura',
            'unit' => 'Toneladas',
            'periodicity' => 'Anual',
            'technical_sheet' => UploadedFile::fake()->create('ficha-tecnica.pdf', 120, 'application/pdf'),
            'data_series_mode' => 'upload',
            'data_file' => UploadedFile::fake()->createWithContent('serie.csv', "dp,municipio,ano,valor\n50,50001,2026,12\n50,50006,2026,18\n11,11001,2026,99\n"),
            'scope_field' => 'dp',
            'scope_values' => '50',
        ])->assertRedirect();

        $indicator = Indicator::query()->where('slug', 'evaluaciones-agropecuarias-municipales')->firstOrFail();
        $this->assertSame(IndicatorStatus::Draft, $indicator->status);
        $this->assertNotNull($indicator->tabular_data_source_id);
        $this->assertSame(2, $indicator->tabularDataSource->currentVersion->row_count);
        $this->assertSame(['field' => 'dp', 'values' => ['50']], $indicator->data_scope_config);
        $this->assertCount(2, $indicator->tabularDataSource->currentVersion->records);
        Storage::disk('local')->assertExists($indicator->technical_sheet_path);

        $this->actingAs($manager)->post(route('admin.indicators.submit', $indicator))->assertRedirect();
        $this->assertSame(IndicatorStatus::PendingReview, $indicator->fresh()->status);

        $this->actingAs($manager)->post(route('admin.indicators.publication.store', $indicator))->assertRedirect();

        $indicator->refresh();
        $this->assertSame(IndicatorStatus::Published, $indicator->status);
        $this->assertSame(1, $indicator->published_version);
        $this->assertDatabaseHas('indicator_versions', [
            'indicator_id' => $indicator->id,
            'version' => 1,
            'approved_by' => $manager->id,
        ]);

        $this->get(route('indicators.show', $indicator))
            ->assertOk()
            ->assertSee('Evaluaciones Agropecuarias Municipales')
            ->assertSee('Ficha técnica oficial')
            ->assertSee('QR del indicador')
            ->assertSee('Serie de datos')
            ->assertSee('Descargar CSV');

        $this->get(route('indicators.technical-sheet', $indicator))->assertOk();
        $download = $this->get(route('indicators.data-series', $indicator))->assertOk();
        $this->assertStringContainsString('municipio', $download->streamedContent());
    }

    public function test_public_indicator_page_links_to_other_published_indicators(): void
    {
        Storage::fake('local');

        $firstSource = $this->source('Población');
        $secondSource = $this->source('Producción');

        $first = Indicator::query()->create([
            'name' => 'Población municipal',
            'slug' => 'poblacion-municipal',
            'summary' => 'Indicador principal.',
            'status' => IndicatorStatus::Published,
            'published_at' => now(),
            'source_type' => 'tabular',
            'tabular_data_source_id' => $firstSource->id,
            'technical_sheet_disk' => 'local',
            'technical_sheet_path' => UploadedFile::fake()->create('poblacion.pdf', 20, 'application/pdf')->store('indicators/poblacion', 'local'),
        ]);
        Indicator::query()->create([
            'name' => 'Producción agrícola',
            'slug' => 'produccion-agricola',
            'summary' => 'Otro indicador.',
            'status' => IndicatorStatus::Published,
            'published_at' => now(),
            'source_type' => 'tabular',
            'tabular_data_source_id' => $secondSource->id,
            'technical_sheet_disk' => 'local',
            'technical_sheet_path' => UploadedFile::fake()->create('agricola.pdf', 20, 'application/pdf')->store('indicators/agricola', 'local'),
        ]);

        $this->get(route('indicators.show', $first))
            ->assertOk()
            ->assertSee('Otros datos publicados')
            ->assertSee('Producción agrícola');
    }

    private function source(string $name): TabularDataSource
    {
        $source = TabularDataSource::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'current_version' => 1,
        ]);
        $source->versions()->create([
            'version' => 1,
            'original_filename' => 'serie.csv',
            'checksum' => str_repeat('a', 64),
            'row_count' => 1,
            'fields' => [
                ['key' => 'codigo', 'label' => 'Código', 'type' => 'integer', 'visibility' => 'public'],
                ['key' => 'valor', 'label' => 'Valor', 'type' => 'integer', 'visibility' => 'analytics'],
            ],
            'records' => [['codigo' => 50001, 'valor' => 10]],
            'validation_summary' => [],
        ]);

        return $source;
    }
}
