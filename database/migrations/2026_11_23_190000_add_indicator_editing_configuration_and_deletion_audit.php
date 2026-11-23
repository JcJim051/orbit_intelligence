<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seguimiento_configuraciones', function (Blueprint $table): void {
            $table->boolean('edicion_validadores_informacion_activa')->default(true);
        });

        Schema::create('indicador_territorial_dato_eliminaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('indicador_territorial_id')->constrained('indicadores_territoriales')->cascadeOnDelete();
            $table->unsignedBigInteger('indicador_territorial_dato_id')->index();
            $table->foreignId('eliminado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->json('datos_anteriores');
            $table->text('justificacion');
            $table->timestamps();

            $table->index(['indicador_territorial_id', 'created_at'], 'indicador_dato_eliminaciones_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicador_territorial_dato_eliminaciones');

        Schema::table('seguimiento_configuraciones', function (Blueprint $table): void {
            $table->dropColumn('edicion_validadores_informacion_activa');
        });
    }
};
