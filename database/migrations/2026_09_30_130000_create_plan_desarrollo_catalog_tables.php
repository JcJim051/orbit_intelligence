<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdd_pilares', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('numeral', 32)->nullable();
            $table->text('nombre');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pdd_ejes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('numeral', 32)->nullable();
            $table->text('nombre');
            $table->foreignId('pilar_id')->constrained('pdd_pilares')->restrictOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pdd_lineas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('numeral', 32)->nullable();
            $table->text('nombre');
            $table->foreignId('eje_id')->constrained('pdd_ejes')->restrictOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pdd_programas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('numeral', 32)->nullable();
            $table->text('nombre');
            $table->foreignId('linea_id')->constrained('pdd_lineas')->restrictOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pdd_subprogramas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('numeral', 32)->nullable();
            $table->text('nombre');
            $table->foreignId('programa_id')->constrained('pdd_programas')->restrictOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sectores_mga', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 8)->unique();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('indicadores_resultado', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->nullable()->unique();
            $table->string('nombre')->unique();
            $table->string('unidad_medida')->nullable();
            $table->string('orientacion', 32)->nullable();
            $table->decimal('linea_base', 18, 4)->nullable();
            $table->string('linea_base_texto')->nullable();
            $table->decimal('meta_cuatrienio', 18, 4)->nullable();
            $table->text('fuente_verificacion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('metas_resultado', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->nullable()->unique();
            $table->string('codigo_provisional', 20)->unique();
            $table->text('descripcion');
            $table->foreignId('programa_id')->nullable()->constrained('pdd_programas')->restrictOnDelete();
            $table->foreignId('subprograma_id')->nullable()->constrained('pdd_subprogramas')->restrictOnDelete();
            $table->foreignId('indicador_resultado_id')->nullable()->constrained('indicadores_resultado')->restrictOnDelete();
            $table->decimal('linea_base', 18, 4)->nullable();
            $table->decimal('meta_cuatrienio', 18, 4)->nullable();
            $table->text('observacion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('metas_producto', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->text('nombre');
            $table->foreignId('subprograma_id')->constrained('pdd_subprogramas')->restrictOnDelete();
            $table->foreignId('sector_mga_id')->constrained('sectores_mga')->restrictOnDelete();
            $table->foreignId('meta_resultado_id')->nullable()->constrained('metas_resultado')->restrictOnDelete();
            $table->foreignId('dependencia_id')->nullable()->constrained('dependencias')->restrictOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metas_producto');
        Schema::dropIfExists('metas_resultado');
        Schema::dropIfExists('indicadores_resultado');
        Schema::dropIfExists('sectores_mga');
        Schema::dropIfExists('pdd_subprogramas');
        Schema::dropIfExists('pdd_programas');
        Schema::dropIfExists('pdd_lineas');
        Schema::dropIfExists('pdd_ejes');
        Schema::dropIfExists('pdd_pilares');
    }
};
