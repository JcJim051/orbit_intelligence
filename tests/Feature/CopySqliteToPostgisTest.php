<?php

namespace Tests\Feature;

use App\Console\Commands\CopySqliteToPostgis;
use Tests\TestCase;

class CopySqliteToPostgisTest extends TestCase
{
    public function test_command_refuses_an_invalid_destination_without_writing(): void
    {
        config(['database.connections.managed_postgis_admin' => config('database.connections.sqlite')]);

        $this->artisan('database:copy-sqlite-to-postgis')
            ->expectsOutput('Se requieren las conexiones legacy_sqlite (SQLite) y managed_postgis_admin (PostgreSQL).')
            ->assertFailed();
    }

    public function test_command_does_not_offer_an_option_to_replace_existing_data(): void
    {
        $command = app(CopySqliteToPostgis::class);

        $this->assertFalse($command->getDefinition()->hasOption('replace-existing'));
    }
}
