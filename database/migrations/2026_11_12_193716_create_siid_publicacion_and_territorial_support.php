<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            $this->extendTerritorialTablesForSqlite();

            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS siid_publicacion');
        DB::statement('CREATE SCHEMA IF NOT EXISTS siid_territorial');

        Schema::create('siid_publicacion.ejes', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre', 180);
            $table->text('descripcion')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('tablero_tipo', 20)->nullable();
            $table->text('tablero_referencia')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('siid_publicacion.areas_tematicas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('eje_id')->constrained('siid_publicacion.ejes')->cascadeOnDelete();
            $table->string('codigo', 60)->nullable();
            $table->string('nombre', 200);
            $table->string('slug', 220)->unique();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['eje_id', 'nombre']);
        });

        Schema::create('siid_publicacion.equivalencias_area', function (Blueprint $table): void {
            $table->id();
            $table->string('texto_origen', 255)->unique();
            $table->string('texto_normalizado', 255)->index();
            $table->foreignId('area_tematica_id')->constrained('siid_publicacion.areas_tematicas')->cascadeOnDelete();
            $table->boolean('activo')->default(true);
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('siid_publicacion.indicadores_siid', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 100)->unique();
            $table->text('nombre');
            $table->foreignId('eje_id')->constrained('siid_publicacion.ejes')->restrictOnDelete();
            $table->foreignId('area_tematica_id')->nullable()->constrained('siid_publicacion.areas_tematicas')->nullOnDelete();
            $table->text('descripcion')->nullable();
            $table->text('fuente')->nullable();
            $table->string('unidad_medida', 160)->nullable();
            $table->string('periodicidad', 120)->nullable();
            $table->text('url_ficha')->nullable();
            $table->text('url_datos')->nullable();
            $table->text('url_tablero')->nullable();
            $table->string('tipo_tablero', 20)->nullable();
            $table->string('procedencia', 120)->nullable();
            $table->string('estado_listado', 80)->nullable();
            $table->string('anio_actualizacion', 40)->nullable();
            $table->text('fuentes_complementarias')->nullable();
            $table->string('pestana_origen', 160);
            $table->boolean('es_icet')->default(false);
            $table->boolean('es_pet')->default(false);
            $table->boolean('activo')->default(true)->index();
            $table->jsonb('datos_origen')->nullable();
            $table->timestamp('sincronizado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('siid_publicacion.indicador_ods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('indicador_siid_id')->constrained('siid_publicacion.indicadores_siid')->cascadeOnDelete();
            $table->foreignId('ods_indicator_id')->nullable()->constrained('ods_indicators')->nullOnDelete();
            $table->string('codigo_origen', 100)->nullable();
            $table->text('nombre_origen')->nullable();
            $table->text('meta_origen')->nullable();
            $table->jsonb('datos_origen')->nullable();
            $table->timestamps();
            $table->unique(['indicador_siid_id', 'codigo_origen']);
        });

        Schema::create('siid_publicacion.sincronizaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('origen', 24)->default('manual');
            $table->string('estado', 24)->default('procesando')->index();
            $table->boolean('dry_run')->default(false);
            $table->unsignedInteger('leidos')->default(0);
            $table->unsignedInteger('creados')->default(0);
            $table->unsignedInteger('actualizados')->default(0);
            $table->unsignedInteger('inactivados')->default(0);
            $table->unsignedInteger('sin_cambios')->default(0);
            $table->unsignedInteger('advertencias')->default(0);
            $table->unsignedInteger('conflictos')->default(0);
            $table->text('error_general')->nullable();
            $table->foreignId('ejecutado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('iniciado_at');
            $table->timestamp('finalizado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('siid_publicacion.sincronizacion_detalles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sincronizacion_id')->constrained('siid_publicacion.sincronizaciones')->cascadeOnDelete();
            $table->string('tipo', 40)->index();
            $table->string('pestana', 160)->nullable();
            $table->unsignedInteger('fila')->nullable();
            $table->string('codigo', 100)->nullable()->index();
            $table->jsonb('datos_recibidos')->nullable();
            $table->text('motivo');
            $table->timestamps();
        });

        Schema::create('siid_publicacion.publicaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('publicable_type');
            $table->unsignedBigInteger('publicable_id');
            $table->boolean('visible')->default(false)->index();
            $table->string('etiqueta', 255)->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('destacado')->default(false);
            $table->string('tablero_tipo', 20)->nullable();
            $table->text('tablero_referencia')->nullable();
            $table->foreignId('publicado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('publicado_at')->nullable();
            $table->timestamps();
            $table->unique(['publicable_type', 'publicable_id']);
            $table->index(['visible', 'orden']);
        });

        Schema::create('siid_territorial.territorios', function (Blueprint $table): void {
            $table->string('codigo', 10)->primary();
            $table->string('nombre', 180);
            $table->string('tipo', 30);
            $table->string('departamento_codigo', 2)->nullable()->index();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('siid_territorial.cargas', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_archivo');
            $table->string('ruta_archivo')->nullable();
            $table->string('estado', 30)->default('previsualizada')->index();
            $table->unsignedInteger('nuevas')->default(0);
            $table->unsignedInteger('modificadas')->default(0);
            $table->unsignedInteger('iguales')->default(0);
            $table->unsignedInteger('duplicadas')->default(0);
            $table->unsignedInteger('erroneas')->default(0);
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aplicado_at')->nullable();
            $table->timestamp('revertido_at')->nullable();
            $table->timestamps();
        });

        Schema::create('siid_territorial.carga_filas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('carga_id')->constrained('siid_territorial.cargas')->cascadeOnDelete();
            $table->unsignedInteger('fila');
            $table->string('clasificacion', 24)->index();
            $table->jsonb('datos_recibidos');
            $table->jsonb('datos_normalizados')->nullable();
            $table->jsonb('datos_anteriores')->nullable();
            $table->text('motivo')->nullable();
            $table->unsignedBigInteger('dato_aplicado_id')->nullable()->index();
            $table->timestamps();
            $table->unique(['carga_id', 'fila']);
        });

        Schema::create('siid_territorial.historial_valores', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('indicador_territorial_dato_id')->index();
            $table->foreignId('carga_id')->nullable()->constrained('siid_territorial.cargas')->nullOnDelete();
            $table->string('accion', 30);
            $table->jsonb('antes')->nullable();
            $table->jsonb('despues')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('indicadores_territoriales', function (Blueprint $table): void {
            $table->string('sentido_deseado', 24)->nullable()->after('unidad_medida');
            $table->foreignId('meta_producto_id')->nullable()->after('dependencia_id')->constrained('metas_producto')->nullOnDelete();
            $table->foreignId('indicador_resultado_id')->nullable()->after('meta_producto_id')->constrained('indicadores_resultado')->nullOnDelete();
            $table->text('url_ficha')->nullable()->after('fuente_informacion');
            $table->string('tablero_tipo', 20)->nullable()->after('url_ficha');
            $table->text('tablero_referencia')->nullable()->after('tablero_tipo');
            $table->boolean('activo')->default(true)->after('estado')->index();
        });

        Schema::table('indicador_territorial_datos', function (Blueprint $table): void {
            $table->dropUnique('indicador_territorial_periodo_unique');
            $table->string('territorio_codigo', 10)->default('50')->after('indicador_territorial_id');
            $table->string('desagregacion_tipo', 80)->default('Total')->after('territorio_codigo');
            $table->string('desagregacion_valor', 180)->default('Total')->after('desagregacion_tipo');
            $table->decimal('numerador', 24, 6)->nullable()->after('valor');
            $table->decimal('denominador', 24, 6)->nullable()->after('numerador');
            $table->boolean('es_preliminar')->default(false)->after('denominador');
            $table->unsignedBigInteger('siid_carga_id')->nullable()->after('archivo_fuente')->index();
            $table->unique(['indicador_territorial_id', 'territorio_codigo', 'periodo', 'desagregacion_tipo', 'desagregacion_valor'], 'indicador_territorial_serie_unique');
        });

        DB::table('siid_territorial.territorios')->insert([
            'codigo' => '50', 'nombre' => 'Meta', 'tipo' => 'departamento', 'departamento_codigo' => '50',
            'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->crearVistas();
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('DROP VIEW IF EXISTS siid_territorial.v_territorial_ultimo_valor');
        DB::statement('DROP VIEW IF EXISTS siid_territorial.v_territorial_serie');

        Schema::table('indicador_territorial_datos', function (Blueprint $table): void {
            $table->dropUnique('indicador_territorial_serie_unique');
            $table->dropColumn(['territorio_codigo', 'desagregacion_tipo', 'desagregacion_valor', 'numerador', 'denominador', 'es_preliminar', 'siid_carga_id']);
            $table->unique(['indicador_territorial_id', 'periodo'], 'indicador_territorial_periodo_unique');
        });

        Schema::table('indicadores_territoriales', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('meta_producto_id');
            $table->dropConstrainedForeignId('indicador_resultado_id');
            $table->dropColumn(['sentido_deseado', 'url_ficha', 'tablero_tipo', 'tablero_referencia', 'activo']);
        });

        foreach (['historial_valores', 'carga_filas', 'cargas', 'territorios'] as $tabla) {
            Schema::dropIfExists('siid_territorial.'.$tabla);
        }

        foreach (['publicaciones', 'sincronizacion_detalles', 'sincronizaciones', 'indicador_ods', 'indicadores_siid', 'equivalencias_area', 'areas_tematicas', 'ejes'] as $tabla) {
            Schema::dropIfExists('siid_publicacion.'.$tabla);
        }

        DB::statement('DROP SCHEMA IF EXISTS siid_territorial');
        DB::statement('DROP SCHEMA IF EXISTS siid_publicacion');
    }

    private function crearVistas(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW siid_territorial.v_territorial_serie AS
            SELECT d.id, i.id AS indicador_id, i.codigo, i.nombre, i.unidad_medida, i.periodicidad,
                   i.dependencia_id, d.territorio_codigo, t.nombre AS territorio, t.tipo AS territorio_tipo,
                   d.desagregacion_tipo, d.desagregacion_valor, d.periodo, d.periodo_inicio, d.periodo_fin,
                   d.valor, d.numerador, d.denominador, d.es_preliminar, d.fecha_corte, d.fuente,
                   d.observacion, d.estado
            FROM public.indicador_territorial_datos d
            JOIN public.indicadores_territoriales i ON i.id = d.indicador_territorial_id
            LEFT JOIN siid_territorial.territorios t ON t.codigo = d.territorio_codigo
            WHERE i.deleted_at IS NULL AND i.activo = true AND d.estado = 'aprobado'
        SQL);

        DB::statement(<<<'SQL'
            CREATE VIEW siid_territorial.v_territorial_ultimo_valor AS
            SELECT DISTINCT ON (indicador_id, territorio_codigo, desagregacion_tipo, desagregacion_valor) *
            FROM siid_territorial.v_territorial_serie
            ORDER BY indicador_id, territorio_codigo, desagregacion_tipo, desagregacion_valor,
                     periodo_fin DESC NULLS LAST, periodo_inicio DESC NULLS LAST, id DESC
        SQL);
    }

    private function extendTerritorialTablesForSqlite(): void
    {
        Schema::table('indicadores_territoriales', function (Blueprint $table): void {
            $table->string('sentido_deseado', 24)->nullable();
            $table->unsignedBigInteger('meta_producto_id')->nullable();
            $table->unsignedBigInteger('indicador_resultado_id')->nullable();
            $table->text('url_ficha')->nullable();
            $table->string('tablero_tipo', 20)->nullable();
            $table->text('tablero_referencia')->nullable();
            $table->boolean('activo')->default(true);
        });
        Schema::table('indicador_territorial_datos', function (Blueprint $table): void {
            $table->string('territorio_codigo', 10)->default('50');
            $table->string('desagregacion_tipo', 80)->default('Total');
            $table->string('desagregacion_valor', 180)->default('Total');
            $table->decimal('numerador', 24, 6)->nullable();
            $table->decimal('denominador', 24, 6)->nullable();
            $table->boolean('es_preliminar')->default(false);
            $table->unsignedBigInteger('siid_carga_id')->nullable();
        });
    }
};
