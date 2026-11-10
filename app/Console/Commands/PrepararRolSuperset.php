<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class PrepararRolSuperset extends Command
{
    protected $signature = 'analitica:preparar-rol';

    protected $description = 'Crea o actualiza el rol Postgres superset_ro, con lectura solo sobre el esquema analitica';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->error('Este comando solo corre sobre PostgreSQL. En SQLite no hay roles.');

            return self::FAILURE;
        }

        $clave = (string) config('superset.ro_password');

        if ($clave === '') {
            $this->error('Defina SUPERSET_RO_PASSWORD antes de crear el rol.');

            return self::FAILURE;
        }

        $literal = str_replace("'", "''", $clave);

        DB::unprepared(<<<SQL
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'superset_ro') THEN
                    CREATE ROLE superset_ro LOGIN PASSWORD '{$literal}' NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT;
                ELSE
                    ALTER ROLE superset_ro WITH LOGIN PASSWORD '{$literal}' NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT;
                END IF;
            END
            $$;
        SQL);

        DB::statement('GRANT USAGE ON SCHEMA analitica TO superset_ro');
        DB::statement('GRANT SELECT ON ALL TABLES IN SCHEMA analitica TO superset_ro');
        DB::statement('ALTER DEFAULT PRIVILEGES IN SCHEMA analitica GRANT SELECT ON TABLES TO superset_ro');

        try {
            DB::statement('REVOKE ALL ON SCHEMA public FROM superset_ro');
        } catch (Throwable) {
            $this->warn('No se pudo revocar el esquema public. El rol igual solo recibió GRANT sobre analitica.');
        }

        $this->info('El rol superset_ro puede leer el esquema analitica.');

        return self::SUCCESS;
    }
}
