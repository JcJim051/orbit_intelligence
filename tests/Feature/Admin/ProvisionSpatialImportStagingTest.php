<?php

namespace Tests\Feature\Admin;

use App\Enums\SpatialImportStatus;
use App\Models\SpatialDataset;
use App\Models\SpatialImport;
use App\Models\SpatialImportContract;
use App\Services\Postgis\ManagedPostgisConfiguration;
use App\Services\Postgis\ProvisionSpatialImportStaging;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use PDO;
use Tests\TestCase;

class ProvisionSpatialImportStagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_qgis_access_maps_only_the_verified_9377_client_identifier(): void
    {
        $import = SpatialImport::factory()->create([
            'database_username' => 'stg_testrole',
            'staging_schema' => 'staging_testrole',
        ]);
        $statements = [];
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('selectOne')->andReturnUsing(function (string $sql): ?object {
            return $sql === 'SELECT current_database() AS database' ? (object) ['database' => 'siid_meta'] : null;
        });
        $connection->shouldReceive('getPdo')->andReturn(new PDO('sqlite::memory:'));
        $connection->shouldReceive('statement')->andReturnUsing(function (string $sql) use (&$statements): bool {
            $statements[] = $sql;

            return true;
        });

        $this->serviceWith($connection)->provision($import);

        $sql = implode("\n", $statements);
        $this->assertStringContainsString('CREATE OR REPLACE FUNCTION "staging_testrole".addgeometrycolumn(', $sql);
        $this->assertStringContainsString("target_schema IS DISTINCT FROM 'staging_testrole'", $sql);
        $this->assertStringContainsString('IF requested_srid = 520003408 THEN', $sql);
        $this->assertStringContainsString('requested_srid := 9377;', $sql);
        $this->assertStringContainsString('RETURN public.AddGeometryColumn(', $sql);
        $this->assertStringContainsString('SECURITY INVOKER', $sql);
        $this->assertStringContainsString('REVOKE ALL ON FUNCTION "staging_testrole".addgeometrycolumn(varchar, varchar, varchar, integer, varchar, integer) FROM PUBLIC', $sql);
        $this->assertStringContainsString('GRANT EXECUTE ON FUNCTION "staging_testrole".addgeometrycolumn(varchar, varchar, varchar, integer, varchar, integer) TO "stg_testrole"', $sql);
        $this->assertStringContainsString('ALTER ROLE "stg_testrole" SET search_path TO "staging_testrole", public', $sql);
        $this->assertStringNotContainsString('IF requested_srid > 998999', $sql);
    }

    public function test_creating_a_contract_only_protects_its_own_table(): void
    {
        $import = SpatialImport::factory()->create([
            'database_username' => 'stg_testrole',
            'staging_schema' => 'staging_testrole',
        ]);
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('selectOne')->once()->with('SELECT current_database() AS database')->andReturn((object) ['database' => 'siid_meta']);
        $connection->shouldReceive('selectOne')->once()->with(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_name = ? AND table_type = 'BASE TABLE'",
            ['staging_testrole', 'capa_contratada'],
        )->andReturn((object) ['table_name' => 'capa_contratada']);
        $connection->shouldReceive('statement')->once()->with('ALTER TABLE "staging_testrole"."capa_contratada" OWNER TO "siid_owner"')->andReturnTrue();
        $connection->shouldReceive('statement')->once()->with('GRANT SELECT, INSERT, UPDATE, DELETE ON "staging_testrole"."capa_contratada" TO "stg_testrole"')->andReturnTrue();
        $connection->shouldReceive('statement')->once()->with('GRANT USAGE, CREATE ON SCHEMA "staging_testrole" TO "stg_testrole"')->andReturnTrue();

        $service = $this->serviceWith($connection);
        $service->freeze($import, 'capa_contratada');
    }

    public function test_renewal_recovers_only_uncontracted_legacy_tables(): void
    {
        $dataset = SpatialDataset::factory()->create();
        $import = SpatialImport::factory()->create([
            'database_username' => 'stg_testrole',
            'staging_schema' => 'staging_testrole',
            'selected_table' => 'capa_inicial',
            'spatial_dataset_id' => $dataset->id,
            'status' => SpatialImportStatus::Approved,
        ]);
        SpatialImportContract::factory()->create([
            'spatial_import_id' => $import->id,
            'source_table' => 'capa_contratada',
        ]);

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('selectOne')->once()->with('SELECT current_database() AS database')->andReturn((object) ['database' => 'siid_meta']);
        $connection->shouldReceive('getPdo')->twice()->andReturn(new PDO('sqlite::memory:'));
        $connection->shouldReceive('statement')->once()->with(Mockery::pattern('/^CREATE OR REPLACE FUNCTION "staging_testrole"\.addgeometrycolumn\(/'))->andReturnTrue();
        $connection->shouldReceive('statement')->once()->with('REVOKE ALL ON FUNCTION "staging_testrole".addgeometrycolumn(varchar, varchar, varchar, integer, varchar, integer) FROM PUBLIC')->andReturnTrue();
        $connection->shouldReceive('statement')->once()->with('GRANT EXECUTE ON FUNCTION "staging_testrole".addgeometrycolumn(varchar, varchar, varchar, integer, varchar, integer) TO "stg_testrole"')->andReturnTrue();
        $connection->shouldReceive('statement')->once()->with(Mockery::pattern('/^ALTER ROLE "stg_testrole" VALID UNTIL /'))->andReturnTrue();
        $connection->shouldReceive('statement')->once()->with('GRANT USAGE, CREATE ON SCHEMA "staging_testrole" TO "stg_testrole"')->andReturnTrue();
        $connection->shouldReceive('statement')->once()->with('GRANT "stg_testrole" TO "siid_owner"')->andReturnTrue();
        $connection->shouldReceive('statement')->once()->with('ALTER ROLE "stg_testrole" SET search_path TO "staging_testrole", public')->andReturnTrue();
        $connection->shouldReceive('select')->once()->with(
            'SELECT tablename, tableowner FROM pg_tables WHERE schemaname = ?',
            ['staging_testrole'],
        )->andReturn([
            (object) ['tablename' => 'capa_inicial', 'tableowner' => 'siid_owner'],
            (object) ['tablename' => 'capa_contratada', 'tableowner' => 'siid_owner'],
            (object) ['tablename' => 'capa_pendiente', 'tableowner' => 'siid_owner'],
            (object) ['tablename' => 'capa_nueva', 'tableowner' => 'stg_testrole'],
            (object) ['tablename' => 'capa_externa', 'tableowner' => 'postgres'],
        ]);
        $connection->shouldReceive('statement')->once()->with('ALTER TABLE "staging_testrole"."capa_pendiente" OWNER TO "stg_testrole"')->andReturnTrue();

        $service = $this->serviceWith($connection);
        $this->assertSame(1, $service->renewAccess($import, now()->addDays(3)));
    }

    private function serviceWith(Connection $connection): ProvisionSpatialImportStaging
    {
        $configuration = Mockery::mock(ManagedPostgisConfiguration::class);
        $configuration->shouldReceive('load')->andReturn([
            'admin_username' => 'siid_owner',
            'database' => 'siid_meta',
        ]);
        $configuration->shouldReceive('configureConnections')->once();
        DB::shouldReceive('purge')->once()->with('managed_postgis_admin');
        DB::shouldReceive('connection')->once()->with('managed_postgis_admin')->andReturn($connection);

        return new ProvisionSpatialImportStaging($configuration);
    }
}
