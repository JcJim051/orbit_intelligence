<?php

namespace Tests\Unit\Services\Investments;

use App\Services\Investments\InvestmentMetricsService;
use PHPUnit\Framework\TestCase;

class InvestmentMetricsServiceTest extends TestCase
{
    public function test_calculates_execution_from_sums_instead_of_averaging_project_percentages(): void
    {
        $service = new InvestmentMetricsService;

        $this->assertSame(25.0, $service->weightedExecutionPercent(50, 200));
        $this->assertNull($service->weightedExecutionPercent(null, 200));
        $this->assertNull($service->weightedExecutionPercent(50, 0));
    }
}
