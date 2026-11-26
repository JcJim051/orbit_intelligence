<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la configuración del mapa del inicio.
 * Si la tabla ya existe (por una versión anterior de esta funcionalidad), no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('micrositio_mapas')) {
            return;
        }

        Schema::create('micrositio_mapas', function (Blueprint $table): void {
            $table->id();
            $table->boolean('mostrar_bloque')->default(true);
            $table->string('capa_principal', 40);
            $table->json('capas_adicionales')->nullable();
            $table->string('periodo_modo', 20)->default('ultimo');
            $table->string('periodo_fijo', 20)->nullable();
            $table->string('paleta', 20)->default('auto');
            $table->string('polaridad', 20)->default('auto');
            $table->string('texto_tarjeta', 160)->default('29 municipios · capital Villavicencio');
            $table->string('texto_cta', 60)->default('Explorar en el visor');
            $table->string('url_cta', 500)->nullable();
            $table->string('url_ficha_tecnica', 500)->nullable();
            $table->string('url_indicadores_municipio', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('micrositio_mapas');
    }
};
