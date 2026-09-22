<?php

namespace Tests\Unit;

use App\Models\TabularDataSourceVersion;
use App\Services\Dashboards\DashboardQueryService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardQueryServiceTest extends TestCase
{
    public function test_postgres_population_area_distribution_groups_by_the_same_expression_it_selects(): void
    {
        $version = new TabularDataSourceVersion;
        $version->forceFill([
            'id' => '01m35testversion00000000000',
            'fields' => [
                ['key' => 'mpio', 'visibility' => 'public'],
                ['key' => 'ano', 'visibility' => 'public'],
                ['key' => 'area_geografica', 'visibility' => 'public'],
                ['key' => 'total', 'visibility' => 'public'],
            ],
        ]);

        DB::shouldReceive('getDriverName')->once()->andReturn('pgsql');
        DB::shouldReceive('select')->once()->withArgs(function (string $sql, array $bindings) use ($version): bool {
            $this->assertStringContainsString("SELECT jsonb_extract_path_text(item, 'area_geografica') AS label", $sql);
            $this->assertStringContainsString("GROUP BY jsonb_extract_path_text(item, 'area_geografica')", $sql);
            $this->assertStringNotContainsString('GROUP BY jsonb_extract_path_text(item, ?)', $sql);
            $this->assertSame([
                'total', 'total', $version->id,
                'mpio', '50001', 'ano', '2026',
                'Cabecera Municipal', 'Centros Poblados y Rural Disperso',
            ], $bindings);

            return true;
        })->andReturn([
            (object) ['label' => 'Cabecera Municipal', 'value' => 40.0],
            (object) ['label' => 'Centros Poblados y Rural Disperso', 'value' => 21.0],
        ]);

        $result = app(DashboardQueryService::class)->run(
            $version,
            ['operation' => 'population_area_distribution', 'field' => 'total', 'year' => '2026'],
            ['mpio' => '50001'],
        );

        $this->assertSame('series', $result['type']);
        $this->assertSame(40.0, $result['rows'][0]['value']);
        $this->assertSame(21.0, $result['rows'][1]['value']);
    }
}
