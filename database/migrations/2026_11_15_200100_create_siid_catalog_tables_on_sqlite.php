<?php

use App\Support\EsquemaSiidSqlite;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * En PostgreSQL el catálogo ya vive en el esquema siid_publicacion.
 * SQLite no tiene esquemas: se adjunta una base con ese nombre para que los modelos resuelvan las mismas tablas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        EsquemaSiidSqlite::asegurar(app()->runningUnitTests() ? ':memory:' : null);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        Schema::dropIfExists('siid_publicacion.publicaciones');
        Schema::dropIfExists('siid_publicacion.indicadores_siid');
        Schema::dropIfExists('siid_publicacion.areas_tematicas');
        Schema::dropIfExists('siid_publicacion.ejes');
    }
};
