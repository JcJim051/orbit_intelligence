<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicadores_territoriales', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 80)->unique();
            $table->text('nombre');
            $table->text('definicion')->nullable();
            $table->text('objetivo')->nullable();
            $table->foreignId('dependencia_id')->nullable()->constrained('dependencias')->nullOnDelete();
            $table->string('dependencia_fuente', 255)->nullable();
            $table->string('area_tematica', 120)->nullable();
            $table->string('tipo_indicador', 120)->nullable();
            $table->string('unidad_medida', 160)->nullable();
            $table->text('formula_calculo')->nullable();
            $table->text('variables_calculo')->nullable();
            $table->string('periodicidad_original', 160)->nullable();
            $table->string('periodicidad', 32)->nullable();
            $table->text('fuente_informacion')->nullable();
            $table->text('responsable_informacion')->nullable();
            $table->text('desagregaciones')->nullable();
            $table->text('cobertura_geografica')->nullable();
            $table->decimal('linea_base_valor', 24, 6)->nullable();
            $table->string('linea_base_periodo', 80)->nullable();
            $table->text('observaciones_tecnicas')->nullable();
            $table->string('grupo_asignacion', 32)->default('sin_asignar');
            $table->string('estado', 40)->default('precargado');
            $table->foreignId('asignado_a')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprobado_at')->nullable();
            $table->string('fuente_importacion', 255)->nullable();
            $table->unsignedInteger('fila_fuente')->nullable();
            $table->timestamps();

            $table->index(['asignado_a', 'estado']);
            $table->index(['dependencia_id', 'periodicidad']);
            $table->index(['grupo_asignacion', 'estado']);
        });

        Schema::create('indicador_territorial_asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicador_territorial_id')->constrained('indicadores_territoriales')->cascadeOnDelete();
            $table->foreignId('asignado_a')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('asignado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('grupo_origen', 32)->nullable();
            $table->text('motivo')->nullable();
            $table->timestamp('asignado_at');
            $table->timestamps();

            $table->index(['indicador_territorial_id', 'asignado_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicador_territorial_asignaciones');
        Schema::dropIfExists('indicadores_territoriales');
    }
};
