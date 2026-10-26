<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trazadores', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 255)->unique();
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('trazador_categorias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trazador_id')->constrained('trazadores')->restrictOnDelete();
            $table->string('nombre', 500);
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['trazador_id', 'nombre']);
        });

        Schema::create('politicas_publicas_departamentales', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 500)->unique();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('proyecto_trazadores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->restrictOnDelete();
            $table->foreignId('trazador_id')->constrained('trazadores')->restrictOnDelete();
            $table->foreignId('politica_publica_departamental_id')->nullable()->constrained('politicas_publicas_departamentales')->nullOnDelete();
            $table->boolean('activo')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['proyecto_id', 'trazador_id']);
        });

        Schema::create('proyecto_trazador_categorias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proyecto_trazador_id')->constrained('proyecto_trazadores')->restrictOnDelete();
            $table->foreignId('trazador_categoria_id')->constrained('trazador_categorias')->restrictOnDelete();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
            $table->unique(['proyecto_trazador_id', 'trazador_categoria_id'], 'proy_traz_cat_unique');
        });

        Schema::table('reportes_proyecto', function (Blueprint $table): void {
            $table->timestamp('trazadores_confirmados_at')->nullable();
            $table->foreignId('trazadores_confirmados_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('justificacion_trazadores')->nullable();
        });

        Schema::create('reporte_proyecto_trazador_distribuciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reporte_proyecto_id')->constrained('reportes_proyecto')->cascadeOnDelete();
            $table->foreignId('proyecto_trazador_id')->constrained('proyecto_trazadores')->restrictOnDelete();
            $table->foreignId('trazador_categoria_id')->constrained('trazador_categorias')->restrictOnDelete();
            $table->foreignId('fuente_financiacion_id')->constrained('fuentes_financiacion')->restrictOnDelete();
            // Fotografía del corte: estas referencias no se recalculan desde la asociación vigente.
            $table->foreignId('trazador_id')->constrained('trazadores')->restrictOnDelete();
            $table->foreignId('politica_publica_departamental_id')->nullable()->constrained('politicas_publicas_departamentales')->nullOnDelete();
            $table->decimal('asignado', 20, 2)->default(0);
            $table->decimal('comprometido', 20, 2)->default(0);
            $table->decimal('obligado', 20, 2)->default(0);
            $table->decimal('pagado', 20, 2)->default(0);
            $table->timestamps();
            $table->unique(['reporte_proyecto_id', 'proyecto_trazador_id', 'trazador_categoria_id', 'fuente_financiacion_id'], 'reporte_trazador_fila_unique');
            $table->index(['trazador_id', 'politica_publica_departamental_id'], 'reporte_trazador_analitica_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporte_proyecto_trazador_distribuciones');
        Schema::table('reportes_proyecto', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('trazadores_confirmados_por');
            $table->dropColumn(['trazadores_confirmados_at', 'justificacion_trazadores']);
        });
        Schema::dropIfExists('proyecto_trazador_categorias');
        Schema::dropIfExists('proyecto_trazadores');
        Schema::dropIfExists('politicas_publicas_departamentales');
        Schema::dropIfExists('trazador_categorias');
        Schema::dropIfExists('trazadores');
    }
};
