<?php

namespace Tests\Feature\Investment;

use App\Models\InvestmentFinancial;
use App\Models\InvestmentProject;
use App\Services\Investments\InvestmentMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestmentPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_execution_mode_only_sums_rows_between_2024_and_2027(): void
    {
        $included = InvestmentProject::factory()->create();
        $outside = InvestmentProject::factory()->create();
        foreach ([[2023, 1000, 900], [2024, 200, 50], [2027, 300, 100], [2028, 2000, 1800]] as [$year, $current, $paid]) {
            InvestmentFinancial::create([
                'investment_project_id' => $included->id,
                'source_dataset_id' => 'v4ap-cvae',
                'source_row_hash' => hash('sha256', $included->id.'-'.$year),
                'fiscal_year' => $year,
                'current_value' => $current,
                'paid_value' => $paid,
                'raw_data' => [],
            ]);
        }
        InvestmentFinancial::create([
            'investment_project_id' => $outside->id,
            'source_dataset_id' => 'v4ap-cvae',
            'source_row_hash' => hash('sha256', $outside->id.'-2023'),
            'fiscal_year' => 2023,
            'raw_data' => [],
        ]);

        $summary = app(InvestmentMetricsService::class)->summary(['period_mode' => 'execution']);

        $this->assertSame(1, $summary['projects']);
        $this->assertSame(500.0, $summary['current_value']);
        $this->assertSame(150.0, $summary['paid_value']);
        $this->assertSame(30.0, $summary['financial_execution_percent']);
    }

    public function test_horizon_mode_includes_projects_that_overlap_the_government_period_once(): void
    {
        InvestmentProject::factory()->create(['horizon_start_year' => 2022, 'horizon_end_year' => 2025, 'total_value' => 600]);
        InvestmentProject::factory()->create(['horizon_start_year' => 2019, 'horizon_end_year' => 2023, 'total_value' => 900]);

        $summary = app(InvestmentMetricsService::class)->summary(['period_mode' => 'horizon']);

        $this->assertSame(1, $summary['projects']);
        $this->assertSame(600.0, $summary['total_value']);
        $this->assertNull($summary['current_value']);
        $this->assertNull($summary['financial_execution_percent']);
    }
}
