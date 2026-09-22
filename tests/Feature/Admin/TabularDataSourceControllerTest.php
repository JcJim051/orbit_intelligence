<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\TabularDataSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

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
}
