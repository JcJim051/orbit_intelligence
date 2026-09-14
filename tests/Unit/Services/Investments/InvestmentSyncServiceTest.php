<?php

namespace Tests\Unit\Services\Investments;

use App\Models\InvestmentSyncRun;
use App\Services\Investments\InvestmentSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InvestmentSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_socrata_sync_updates_natural_keys_without_duplicate_rows(): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/api/views/')) {
                return Http::response(['name' => 'Fuente DNP', 'rowsUpdatedAt' => 1788887616]);
            }
            if (str_contains($request->url(), '/resource/cf9k-55fw.json')) {
                return Http::response([[
                    'bpin' => '2026005500001', 'nombreproyecto' => 'Proyecto real de prueba contractual',
                    'entidadresponsable' => 'Meta', 'codigoentidadresponsable' => '50', 'sector' => 'Transporte',
                    'valortotalproyecto' => '1000.00', 'valorvigenteproyecto' => '800.00',
                ]]);
            }
            if (str_contains($request->url(), '/resource/v4ap-cvae.json')) {
                return Http::response([[
                    'bpin' => '2026005500001', 'vigencia' => '2026', 'fuentedefinanciaci_n' => 'Propios',
                    'entidadfuentefinanciacion' => 'Meta', 'tiporecursofuentefinanciacion' => 'Propios territorio',
                    'valorvigente' => '800.00', 'valorcomprometido' => '600.00', 'valorobligado' => '500.00', 'valorpagado' => '400.00',
                ]]);
            }
            if (str_contains($request->url(), '/resource/7mxf-bp6x.json')) {
                return Http::response([[
                    'bpin' => '2026005500001', 'avancefisico' => '45.5', 'avancefinanciero' => '38.2', 'valorvigente' => '800.00',
                ]]);
            }

            return Http::response([]);
        });

        app(InvestmentSyncService::class)->sync($this->newSyncRun());
        app(InvestmentSyncService::class)->sync($this->newSyncRun());

        $this->assertDatabaseCount('investment_projects', 1);
        $this->assertDatabaseCount('investment_financials', 1);
        $this->assertDatabaseCount('investment_progress_reports', 1);
        $this->assertDatabaseHas('investment_projects', ['bpin' => '2026005500001', 'total_value' => 1000]);
    }

    private function newSyncRun(): InvestmentSyncRun
    {
        return InvestmentSyncRun::create([
            'universe' => 'governor', 'status' => 'pending', 'datasets' => array_keys(config('investments.datasets')), 'project_limit' => 1,
        ]);
    }
}
