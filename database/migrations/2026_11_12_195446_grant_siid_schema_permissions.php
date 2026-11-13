<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        $app = $this->identifier((string) config('database.connections.managed_postgis.username'));
        $readerName = (string) config('database.managed_roles.reader');

        foreach (['siid_publicacion', 'siid_territorial'] as $schema) {
            DB::statement("GRANT USAGE ON SCHEMA {$schema} TO {$app}");
            DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA {$schema} TO {$app}");
            DB::statement("GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA {$schema} TO {$app}");
            DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA {$schema} GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO {$app}");
            DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA {$schema} GRANT USAGE, SELECT ON SEQUENCES TO {$app}");
        }

        if ($readerName !== '') {
            $reader = $this->identifier($readerName);
            DB::statement("GRANT USAGE ON SCHEMA siid_territorial TO {$reader}");
            DB::statement("GRANT SELECT ON siid_territorial.v_territorial_serie, siid_territorial.v_territorial_ultimo_valor TO {$reader}");
        }
    }

    public function down(): void
    {
        // No revocar automáticamente: estos roles pueden servir a otros componentes desplegados.
    }

    private function identifier(string $value): string
    {
        if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $value)) {
            throw new RuntimeException('El rol PostgreSQL configurado no es válido.');
        }

        return '"'.$value.'"';
    }
};
