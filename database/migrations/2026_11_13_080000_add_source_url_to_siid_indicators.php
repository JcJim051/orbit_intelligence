<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('siid_publicacion.indicadores_siid', function (Blueprint $table): void {
            $table->text('url_fuente')->nullable()->after('fuente');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            Schema::table('siid_publicacion.indicadores_siid', function (Blueprint $table): void {
                $table->dropColumn('url_fuente');
            });
        }
    }
};
