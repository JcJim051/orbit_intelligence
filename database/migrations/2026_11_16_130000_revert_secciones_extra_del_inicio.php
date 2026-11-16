<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deshace el esquema de las secciones que se retiraron del inicio
 * (cifras dinámicas, campos extra del hero, últimos, aliados y textos).
 * Conserva micrositio_mapas. En una base que nunca tuvo esas tablas, no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('micrositio_cifras') && Schema::hasColumn('micrositio_cifras', 'origen')) {
            Schema::table('micrositio_cifras', function (Blueprint $table): void {
                $table->dropColumn(['origen', 'criterio', 'etiqueta']);
            });
        }

        if (Schema::hasTable('micrositio_heroes') && Schema::hasColumn('micrositio_heroes', 'eyebrow')) {
            Schema::table('micrositio_heroes', function (Blueprint $table): void {
                $table->dropColumn(['eyebrow', 'fuentes_linea', 'pildoras', 'usar_imagen_fondo']);
            });
        }

        Schema::dropIfExists('micrositio_ultimos');
        Schema::dropIfExists('micrositio_aliados');
        Schema::dropIfExists('micrositio_textos_inicio');
    }

    public function down(): void
    {
        if (Schema::hasTable('micrositio_cifras') && ! Schema::hasColumn('micrositio_cifras', 'origen')) {
            Schema::table('micrositio_cifras', function (Blueprint $table): void {
                $table->string('origen', 40)->default('manual');
                $table->string('criterio', 30)->default('activos');
                $table->string('etiqueta', 120)->nullable();
            });
        }

        if (Schema::hasTable('micrositio_heroes') && ! Schema::hasColumn('micrositio_heroes', 'eyebrow')) {
            Schema::table('micrositio_heroes', function (Blueprint $table): void {
                $table->string('eyebrow', 120)->nullable();
                $table->string('fuentes_linea', 255)->nullable();
                $table->json('pildoras')->nullable();
                $table->boolean('usar_imagen_fondo')->default(false);
            });
        }
    }
};
