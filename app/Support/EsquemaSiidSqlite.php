<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EsquemaSiidSqlite
{
    public static function asegurar(?string $archivo = null): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        $conectado = collect(DB::select('PRAGMA database_list'))
            ->contains(fn (object $base): bool => $base->name === 'siid_publicacion');

        if (! $conectado) {
            $destino = $archivo ?? (app()->runningUnitTests()
                ? ':memory:'
                : storage_path('framework/siid_publicacion.sqlite'));

            if ($destino !== ':memory:') {
                $directorio = dirname($destino);
                if (! is_dir($directorio)) {
                    mkdir($directorio, 0775, true);
                }
                if (! is_file($destino)) {
                    touch($destino);
                }
            }

            DB::statement('ATTACH DATABASE '.DB::connection()->getPdo()->quote($destino).' AS siid_publicacion');
        }

        if (Schema::hasTable('siid_publicacion.indicadores_siid')) {
            return;
        }

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
            $table->unsignedBigInteger('eje_id');
            $table->string('codigo', 60)->nullable();
            $table->string('nombre', 200);
            $table->string('slug', 220)->unique();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('siid_publicacion.indicadores_siid', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 100)->unique();
            $table->text('nombre');
            $table->unsignedBigInteger('eje_id');
            $table->unsignedBigInteger('area_tematica_id')->nullable();
            $table->text('descripcion')->nullable();
            $table->text('fuente')->nullable();
            $table->string('unidad_medida', 160)->nullable();
            $table->string('periodicidad', 120)->nullable();
            $table->text('url_ficha')->nullable();
            $table->text('url_datos')->nullable();
            $table->text('url_tablero')->nullable();
            $table->string('tipo_tablero', 20)->nullable();
            $table->string('pestana_origen', 160);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('siid_publicacion.publicaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('publicable_type');
            $table->unsignedBigInteger('publicable_id');
            $table->boolean('visible')->default(false);
            $table->string('etiqueta')->nullable();
            $table->text('descripcion_publica')->nullable();
            $table->text('fuente_publica')->nullable();
            $table->string('periodicidad_publica')->nullable();
            $table->string('imagen_path')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('destacado')->default(false);
            $table->boolean('mostrar_serie')->default(false);
            $table->string('tablero_tipo', 20)->nullable();
            $table->text('tablero_referencia')->nullable();
            $table->timestamps();
            $table->unique(['publicable_type', 'publicable_id']);
        });
    }
}
