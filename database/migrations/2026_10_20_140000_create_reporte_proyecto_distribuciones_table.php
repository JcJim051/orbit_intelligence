<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporte_proyecto_distribuciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reporte_proyecto_id')->constrained('reportes_proyecto')->cascadeOnDelete();
            $table->foreignId('actividad_id')->nullable()->constrained('actividades')->restrictOnDelete();
            $table->foreignId('fuente_financiacion_id')->constrained('fuentes_financiacion')->restrictOnDelete();
            $table->foreignId('municipio_id')->constrained('municipios')->restrictOnDelete();
            $table->decimal('programado', 20, 2)->default(0);
            $table->decimal('comprometido', 20, 2)->default(0);
            $table->decimal('obligado', 20, 2)->default(0);
            $table->decimal('pagado', 20, 2)->default(0);
            $table->timestamps();
            $table->unique(['reporte_proyecto_id', 'actividad_id', 'fuente_financiacion_id', 'municipio_id'], 'reporte_distribucion_llave');
            $table->index(['reporte_proyecto_id', 'municipio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporte_proyecto_distribuciones');
    }
};
