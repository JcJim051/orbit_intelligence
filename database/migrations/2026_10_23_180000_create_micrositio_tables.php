<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('micrositio_secciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 40)->unique();
            $table->json('publicado')->nullable();
            $table->timestamp('publicado_en')->nullable();
            $table->timestamps();
        });

        Schema::create('micrositio_heroes', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('subtitulo');
            $table->string('banner_path')->nullable();
            $table->timestamps();
        });

        Schema::create('micrositio_cifras', function (Blueprint $table) {
            $table->id();
            $table->string('valor');
            $table->string('descripcion');
            $table->string('fuente');
            $table->date('fecha_corte');
            $table->boolean('es_ejemplo')->default(false);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('micrositio_temas', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('nombre');
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('micrositio_indicadores', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre');
            $table->foreignId('micrositio_tema_id')->constrained('micrositio_temas')->restrictOnDelete();
            $table->uuid('dashboard_uuid')->nullable();
            $table->text('descripcion');
            $table->boolean('es_publico')->default(false);
            $table->boolean('es_ejemplo')->default(false);
            $table->string('conjunto_datos', 40)->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('micrositio_publicaciones', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 80);
            $table->string('titulo');
            $table->date('fecha');
            $table->text('resumen');
            $table->string('archivo_path')->nullable();
            $table->string('nombre_archivo')->nullable();
            $table->boolean('es_ejemplo')->default(false);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('micrositio_menus', function (Blueprint $table) {
            $table->id();
            $table->string('texto', 120);
            $table->string('url', 500);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('micrositio_pies', function (Blueprint $table) {
            $table->id();
            $table->string('direccion');
            $table->string('horario');
            $table->string('conmutador', 80);
            $table->string('linea_gratuita', 80);
            $table->string('anticorrupcion', 80);
            $table->string('correo');
            $table->string('judiciales');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('micrositio_pies');
        Schema::dropIfExists('micrositio_menus');
        Schema::dropIfExists('micrositio_publicaciones');
        Schema::dropIfExists('micrositio_indicadores');
        Schema::dropIfExists('micrositio_temas');
        Schema::dropIfExists('micrositio_cifras');
        Schema::dropIfExists('micrositio_heroes');
        Schema::dropIfExists('micrositio_secciones');
    }
};
