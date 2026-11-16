<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * En PostgreSQL el catálogo SIID ya está en el esquema siid_publicacion.
 * SQLite no tiene esquemas: las pruebas adjuntan una base en memoria para poder consultar esas tablas.
 * En Postgres esta migración no cambia nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        $conectado = collect(DB::select('PRAGMA database_list'))
            ->contains(fn (object $base): bool => $base->name === 'siid_publicacion');

        if (! $conectado) {
            DB::statement("ATTACH DATABASE ':memory:' AS siid_publicacion");
        }

        if (Schema::hasTable('siid_publicacion.publicaciones')) {
            return;
        }

        Schema::create('siid_publicacion.ejes', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 180);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('siid_publicacion.areas_tematicas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('eje_id');
            $table->string('nombre', 200);
            $table->string('slug', 220);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('siid_publicacion.indicadores_siid', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 100);
            $table->text('nombre');
            $table->unsignedBigInteger('eje_id')->nullable();
            $table->unsignedBigInteger('area_tematica_id')->nullable();
            $table->boolean('activo')->default(true);
            $table->string('pestana_origen', 160)->default('prueba');
            $table->timestamps();
        });

        Schema::create('siid_publicacion.publicaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('publicable_type');
            $table->unsignedBigInteger('publicable_id');
            $table->boolean('visible')->default(false);
            $table->boolean('destacado')->default(false);
            $table->unsignedInteger('orden')->default(0);
            $table->string('etiqueta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        Schema::dropIfExists('siid_publicacion.publicaciones');
        Schema::dropIfExists('siid_publicacion.indicadores_siid');
        Schema::dropIfExists('siid_publicacion.areas_tematicas');
        Schema::dropIfExists('siid_publicacion.ejes');
    }
};
