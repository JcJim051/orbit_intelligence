<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reporte mensual sectorial: proyectos (BPIN) por dependencia, seguimientos (cortes),
     * pasivas del PCT, techos derivados, actividades, ejecución, avance físico, evidencias y focalización.
     */
    public function up(): void
    {
        Schema::create('dependencia_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['dependencia_id', 'user_id']);
        });

        Schema::create('proyectos', function (Blueprint $table) {
            $table->id();
            $table->string('bpin', 20)->unique();
            $table->text('nombre');
            $table->string('tipo_focalizacion', 32)->nullable();
            $table->foreignId('municipio_id')->nullable()->constrained('municipios')->restrictOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dependencia_proyecto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependencia_id')->constrained('dependencias')->restrictOnDelete();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->boolean('es_responsable_principal')->default(false);
            $table->string('origen', 32)->default('pasiva');
            $table->timestamps();
            $table->unique(['dependencia_id', 'proyecto_id']);
            $table->index('proyecto_id');
        });

        Schema::create('meta_producto_proyecto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_producto_id')->constrained('metas_producto')->restrictOnDelete();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['meta_producto_id', 'proyecto_id']);
        });

        Schema::create('seguimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('vigencia');
            $table->unsignedTinyInteger('mes');
            $table->date('fecha_corte');
            $table->string('estado', 32)->default('abierto');
            $table->text('observacion')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cerrado_at')->nullable();
            $table->timestamps();
            $table->unique(['vigencia', 'mes']);
        });

        Schema::create('pasiva_cargas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seguimiento_id')->constrained('seguimientos')->restrictOnDelete();
            $table->string('disk', 40);
            $table->string('path');
            $table->string('nombre_original');
            $table->string('mime', 160)->nullable();
            $table->unsignedBigInteger('bytes');
            $table->char('sha256', 64);
            $table->string('base_techo', 40);
            $table->boolean('es_vigente')->default(true);
            $table->unsignedInteger('lineas_total')->default(0);
            $table->unsignedInteger('lineas_inversion')->default(0);
            $table->unsignedInteger('lineas_pendientes')->default(0);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['seguimiento_id', 'es_vigente']);
        });

        Schema::create('techos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seguimiento_id')->constrained('seguimientos')->restrictOnDelete();
            $table->foreignId('proyecto_id')->constrained('proyectos')->restrictOnDelete();
            $table->foreignId('fuente_financiacion_id')->constrained('fuentes_financiacion')->restrictOnDelete();
            $table->foreignId('dependencia_id')->constrained('dependencias')->restrictOnDelete();
            $table->decimal('valor_pasiva', 20, 2)->default(0);
            $table->decimal('valor_ajuste', 20, 2)->nullable();
            $table->decimal('valor', 20, 2)->default(0);
            $table->string('base', 40);
            $table->foreignId('pasiva_carga_id')->nullable()->constrained('pasiva_cargas')->restrictOnDelete();
            $table->unsignedInteger('lineas_count')->default(0);
            $table->timestamps();
            $table->unique(['seguimiento_id', 'proyecto_id', 'fuente_financiacion_id', 'dependencia_id'], 'techos_llave_unica');
            $table->index(['seguimiento_id', 'dependencia_id']);
        });

        Schema::create('pasiva_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pasiva_carga_id')->constrained('pasiva_cargas')->cascadeOnDelete();
            $table->foreignId('seguimiento_id')->constrained('seguimientos')->restrictOnDelete();
            $table->unsignedInteger('fila');
            $table->string('identificacion_presupuestal');
            $table->string('unidad_pct', 12)->nullable();
            $table->string('rubro', 120)->nullable();
            $table->string('codigo_fuente', 20)->nullable();
            $table->text('concepto')->nullable();
            $table->string('bpin', 20)->nullable();
            $table->text('nombre_proyecto')->nullable();
            $table->boolean('es_inversion')->default(false);
            $table->foreignId('fuente_financiacion_id')->nullable()->constrained('fuentes_financiacion')->restrictOnDelete();
            $table->foreignId('dependencia_id')->nullable()->constrained('dependencias')->restrictOnDelete();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->restrictOnDelete();
            $table->foreignId('techo_id')->nullable()->constrained('techos')->nullOnDelete();
            $table->decimal('apropiacion_inicial', 20, 2)->default(0);
            $table->decimal('modificaciones', 20, 2)->default(0);
            $table->decimal('apropiacion_definitiva', 20, 2)->default(0);
            $table->decimal('cdp', 20, 2)->default(0);
            $table->decimal('compromisos', 20, 2)->default(0);
            $table->decimal('obligaciones', 20, 2)->default(0);
            $table->decimal('pagos', 20, 2)->default(0);
            $table->string('estado_revision', 32);
            $table->json('motivos_revision')->nullable();
            $table->timestamps();
            $table->index(['seguimiento_id', 'dependencia_id']);
            $table->index(['seguimiento_id', 'estado_revision']);
            $table->index(['proyecto_id', 'fuente_financiacion_id']);
        });

        Schema::create('techo_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('techo_id')->constrained('techos')->cascadeOnDelete();
            $table->foreignId('seguimiento_id')->constrained('seguimientos')->restrictOnDelete();
            $table->foreignId('dependencia_id')->constrained('dependencias')->restrictOnDelete();
            $table->decimal('valor_anterior', 20, 2)->nullable();
            $table->decimal('valor_nuevo', 20, 2);
            $table->string('origen', 32);
            $table->text('motivo');
            $table->foreignId('pasiva_carga_id')->nullable()->constrained('pasiva_cargas')->restrictOnDelete();
            $table->string('soporte_disk', 40)->nullable();
            $table->string('soporte_path')->nullable();
            $table->string('soporte_nombre_original')->nullable();
            $table->char('soporte_sha256', 64)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('actividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->restrictOnDelete();
            $table->foreignId('dependencia_id')->constrained('dependencias')->restrictOnDelete();
            $table->foreignId('meta_producto_id')->nullable()->constrained('metas_producto')->restrictOnDelete();
            $table->string('codigo', 40)->nullable();
            $table->text('nombre');
            $table->string('unidad_medida', 120)->nullable();
            $table->decimal('cantidad_programada', 20, 4)->nullable();
            $table->boolean('activo')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['proyecto_id', 'dependencia_id']);
        });

        Schema::create('actividad_programaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actividad_id')->constrained('actividades')->cascadeOnDelete();
            $table->foreignId('fuente_financiacion_id')->constrained('fuentes_financiacion')->restrictOnDelete();
            $table->unsignedSmallInteger('vigencia');
            $table->decimal('valor_asignado', 20, 2)->default(0);
            $table->timestamps();
            $table->unique(['actividad_id', 'fuente_financiacion_id', 'vigencia'], 'programacion_llave_unica');
        });

        Schema::create('reportes_proyecto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seguimiento_id')->constrained('seguimientos')->restrictOnDelete();
            $table->foreignId('proyecto_id')->constrained('proyectos')->restrictOnDelete();
            $table->foreignId('dependencia_id')->constrained('dependencias')->restrictOnDelete();
            $table->string('estado', 32)->default('borrador');
            $table->text('justificacion_focalizacion')->nullable();
            $table->foreignId('reportado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reportado_at')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('revisado_at')->nullable();
            $table->text('observacion_revision')->nullable();
            $table->timestamps();
            $table->unique(['seguimiento_id', 'proyecto_id', 'dependencia_id'], 'reportes_proyecto_llave_unica');
            $table->index(['seguimiento_id', 'dependencia_id']);
        });

        Schema::create('ejecuciones_financieras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_proyecto_id')->constrained('reportes_proyecto')->cascadeOnDelete();
            $table->foreignId('actividad_id')->constrained('actividades')->restrictOnDelete();
            $table->foreignId('fuente_financiacion_id')->constrained('fuentes_financiacion')->restrictOnDelete();
            $table->decimal('comprometido', 20, 2)->default(0);
            $table->decimal('obligado', 20, 2)->default(0);
            $table->decimal('pagado', 20, 2)->default(0);
            $table->timestamps();
            $table->unique(['reporte_proyecto_id', 'actividad_id', 'fuente_financiacion_id'], 'ejecuciones_llave_unica');
        });

        Schema::create('avances_fisicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_proyecto_id')->constrained('reportes_proyecto')->cascadeOnDelete();
            $table->foreignId('actividad_id')->constrained('actividades')->restrictOnDelete();
            $table->decimal('cantidad', 20, 4)->default(0);
            $table->date('fecha_ejecucion')->nullable();
            $table->text('descripcion')->nullable();
            $table->timestamps();
            $table->unique(['reporte_proyecto_id', 'actividad_id'], 'avances_llave_unica');
        });

        Schema::create('evidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('avance_fisico_id')->constrained('avances_fisicos')->cascadeOnDelete();
            $table->string('disk', 40);
            $table->string('path');
            $table->string('nombre_original');
            $table->string('mime', 160)->nullable();
            $table->unsignedBigInteger('bytes');
            $table->char('sha256', 64);
            $table->text('descripcion')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('focalizaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_proyecto_id')->constrained('reportes_proyecto')->cascadeOnDelete();
            $table->foreignId('municipio_id')->constrained('municipios')->restrictOnDelete();
            $table->decimal('porcentaje', 7, 4);
            $table->decimal('valor', 20, 2)->nullable();
            $table->decimal('cantidad', 20, 4)->nullable();
            $table->timestamps();
            $table->unique(['reporte_proyecto_id', 'municipio_id'], 'focalizaciones_llave_unica');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('focalizaciones');
        Schema::dropIfExists('evidencias');
        Schema::dropIfExists('avances_fisicos');
        Schema::dropIfExists('ejecuciones_financieras');
        Schema::dropIfExists('reportes_proyecto');
        Schema::dropIfExists('actividad_programaciones');
        Schema::dropIfExists('actividades');
        Schema::dropIfExists('techo_historial');
        Schema::dropIfExists('pasiva_lineas');
        Schema::dropIfExists('techos');
        Schema::dropIfExists('pasiva_cargas');
        Schema::dropIfExists('seguimientos');
        Schema::dropIfExists('meta_producto_proyecto');
        Schema::dropIfExists('dependencia_proyecto');
        Schema::dropIfExists('proyectos');
        Schema::dropIfExists('dependencia_user');
    }
};
