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
            $table->text('descripcion_publica')->nullable()->after('etiqueta');
            $table->text('fuente_publica')->nullable()->after('descripcion_publica');
            $table->string('periodicidad_publica', 160)->nullable()->after('fuente_publica');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            Schema::table('siid_publicacion.publicaciones', function (Blueprint $table): void {
                $table->dropColumn(['descripcion_publica', 'fuente_publica', 'periodicidad_publica']);
            });
        }
    }
};
