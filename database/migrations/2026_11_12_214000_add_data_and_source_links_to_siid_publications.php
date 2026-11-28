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

        Schema::table('siid_publicacion.publicaciones', function (Blueprint $table): void {
            $table->text('url_serie_datos')->nullable()->after('imagen_path');
            $table->text('url_fuente')->nullable()->after('url_serie_datos');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            Schema::table('siid_publicacion.publicaciones', function (Blueprint $table): void {
                $table->dropColumn(['url_serie_datos', 'url_fuente']);
            });
        }
    }
};
