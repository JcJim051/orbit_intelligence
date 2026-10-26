<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicador_territorial_datos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicador_territorial_id')->constrained('indicadores_territoriales')->cascadeOnDelete();
            $table->string('periodo', 80);
            $table->date('periodo_inicio')->nullable();
            $table->date('periodo_fin')->nullable();
            $table->decimal('valor', 24, 6);
            $table->date('fecha_corte')->nullable();
            $table->text('fuente')->nullable();
            $table->text('enlace_evidencia')->nullable();
            $table->text('observacion')->nullable();
            $table->string('estado', 24)->default('pendiente');
            $table->string('origen', 32)->default('carga_historica');
            $table->string('archivo_fuente', 255)->nullable();
            $table->unsignedInteger('fila_fuente')->nullable();
            $table->foreignId('reportado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validado_at')->nullable();
            $table->timestamps();

            $table->unique(['indicador_territorial_id', 'periodo'], 'indicador_territorial_periodo_unique');
            $table->index(['indicador_territorial_id', 'estado', 'periodo_inicio'], 'indicador_territorial_datos_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicador_territorial_datos');
    }
};
