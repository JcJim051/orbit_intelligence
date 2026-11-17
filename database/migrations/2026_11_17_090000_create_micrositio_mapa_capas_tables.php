<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('micrositio_mapa_capas', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre');
            $table->string('rotulo_corto');
            $table->string('unidad', 30);
            $table->string('unidad_etiqueta', 40);
            $table->string('periodo', 20);
            $table->unsignedInteger('periodo_orden');
            $table->string('fuente');
            $table->string('fuente_dataset_id', 9)->nullable()->index();
            $table->text('fuente_url');
            $table->timestampTz('fuente_actualizada_en')->nullable();
            $table->timestampTz('sincronizada_en')->nullable();
            $table->string('polaridad', 20)->default('neutral');
            $table->unsignedTinyInteger('decimales')->default(0);
            $table->decimal('valor_departamental', 22, 4)->nullable();
            $table->decimal('valor_anterior_departamental', 22, 4)->nullable();
            $table->text('nota')->nullable();
            $table->json('valores');
            $table->string('checksum', 64);
            $table->string('estado', 20)->default('lista')->index();
            $table->text('ultimo_error')->nullable();
            $table->boolean('activa')->default(true)->index();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestampsTz();
        });

        Schema::create('micrositio_mapa_sincronizaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('fuente', 30)->index();
            $table->string('capa_codigo', 40)->nullable()->index();
            $table->string('estado', 20)->index();
            $table->string('periodo', 20)->nullable();
            $table->unsignedInteger('registros_recibidos')->default(0);
            $table->unsignedInteger('registros_procesados')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->text('error')->nullable();
            $table->timestampTz('iniciada_en');
            $table->timestampTz('finalizada_en')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('micrositio_mapa_sincronizaciones');
        Schema::dropIfExists('micrositio_mapa_capas');
    }
};
