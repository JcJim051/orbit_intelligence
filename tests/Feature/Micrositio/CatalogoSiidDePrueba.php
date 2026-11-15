<?php

namespace Tests\Feature\Micrositio;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CatalogoSiidDePrueba
{
    public static function asegurar(): void
    {
        $conectado = collect(DB::select('PRAGMA database_list'))
            ->contains(fn (object $base): bool => $base->name === 'siid_publicacion');

        if (! $conectado) {
            DB::statement("ATTACH DATABASE ':memory:' AS siid_publicacion");
        }

        if (Schema::hasTable('siid_publicacion.indicadores_siid')) {
            DB::table('siid_publicacion.publicaciones')->delete();
            DB::table('siid_publicacion.indicadores_siid')->delete();
            DB::table('siid_publicacion.areas_tematicas')->delete();
            DB::table('siid_publicacion.ejes')->delete();

            return;
        }

        Schema::create('siid_publicacion.ejes', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre', 180);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('siid_publicacion.areas_tematicas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('eje_id');
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
            $table->string('pestana_origen', 160)->default('prueba');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('siid_publicacion.publicaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('publicable_type');
            $table->unsignedBigInteger('publicable_id');
            $table->boolean('visible')->default(false);
            $table->string('etiqueta')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('destacado')->default(false);
            $table->timestamps();
        });
    }
}
