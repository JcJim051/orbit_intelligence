<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\TabularDataSource;
use App\Models\User;
use App\Services\Dashboards\TabularFileReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class TabularDataSourceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_imports_csv_and_population_differences_are_reported_without_changing_values(): void
    {
        $manager = User::factory()->create(['role' => UserRole::SiidManager]);
        $file = UploadedFile::fake()->createWithContent('poblacion.csv', "codigo_dane,poblacion_total,poblacion_femenina,poblacion_masculina\n50001,100,60,50\n");

        $this->actingAs($manager)->post(route('admin.data-sources.store'), [
            'name' => 'Población municipal', 'slug' => 'poblacion-municipal', 'file' => $file,
        ])->assertRedirect()->assertSessionHas('status');

        $source = TabularDataSource::query()->firstOrFail();
        $version = $source->currentVersion()->firstOrFail();
        $this->assertSame('100', $version->records[0]['poblacion_total']);
        $this->assertSame(1, $version->validation_summary['population_mismatch_count']);
    }

    public function test_xlsx_reader_resolves_namespaced_cell_references_and_shared_headers(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'siid-xlsx-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Código DANE</t></si><si><t>Población total</t></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c></row><row r="2"><c r="A2"><v>50001</v></c><c r="B2"><v>100</v></c></row></sheetData></worksheet>');
        $zip->close();

        $file = new UploadedFile($path, 'poblacion.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $result = app(TabularFileReader::class)->read($file);

        $this->assertSame(['codigo_dane', 'poblacion_total'], array_column($result['fields'], 'key'));
        $this->assertSame(['codigo_dane' => '50001', 'poblacion_total' => '100'], $result['records'][0]);
        @unlink($path);
    }
}
