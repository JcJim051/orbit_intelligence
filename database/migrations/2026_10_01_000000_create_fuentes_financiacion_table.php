<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de fuentes de financiación del PCT. Si la tabla ya existe solo agrega la columna
     * nullable grupo_reporte (recursos_propios, sgr, nacion, otros) que agrupa los techos en la pantalla del sector.
     */
    public function up(): void
    {
        if (! Schema::hasTable('fuentes_financiacion')) {
            Schema::create('fuentes_financiacion', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 20)->unique();
                $table->string('nombre');
                $table->string('tipo')->nullable();
                $table->string('grupo_reporte', 32)->nullable()->index();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });

            return;
        }

        if (! Schema::hasColumn('fuentes_financiacion', 'grupo_reporte')) {
            Schema::table('fuentes_financiacion', function (Blueprint $table) {
                $table->string('grupo_reporte', 32)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuentes_financiacion');
    }
};
