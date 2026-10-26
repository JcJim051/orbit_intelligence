<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicador_territorial_dato_ediciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicador_territorial_dato_id')->constrained('indicador_territorial_datos')->cascadeOnDelete();
            $table->foreignId('editado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->json('datos_anteriores');
            $table->json('datos_nuevos');
            $table->text('justificacion')->nullable();
            $table->timestamps();

            $table->index(['indicador_territorial_dato_id', 'created_at'], 'indicador_dato_ediciones_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicador_territorial_dato_ediciones');
    }
};
