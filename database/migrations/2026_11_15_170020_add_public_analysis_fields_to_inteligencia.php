<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasColumn('inteligencia.capa', 'disponible_analisis_publico')) {
            Schema::table('inteligencia.capa', function (Blueprint $table): void {
                $table->boolean('disponible_analisis_publico')->default(false)->index();
            });
        }
        DB::statement('ALTER TABLE inteligencia.analisis_area ALTER COLUMN user_id DROP NOT NULL');

        if (! Schema::hasTable('inteligencia.analisis_sesion_publica')) {
            Schema::create('inteligencia.analisis_sesion_publica', function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->string('token_hash', 64)->unique();
                $table->string('ip_hash', 64)->index();
                $table->string('estado', 24)->default('pendiente')->index();
                $table->string('origen_geometria', 20);
                $table->string('nombre_archivo')->nullable();
                $table->text('geojson')->nullable();
                $table->unsignedBigInteger('analisis_area_id')->nullable()->unique();
                $table->text('error_publico')->nullable();
                $table->timestamp('expires_at')->index();
                $table->timestamps();
                $table->foreign('analisis_area_id')->references('id')->on('inteligencia.analisis_area')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('inteligencia.analisis_sesion_publica');
        DB::statement('ALTER TABLE inteligencia.analisis_area ALTER COLUMN user_id SET NOT NULL');
        Schema::table('inteligencia.capa', function (Blueprint $table): void {
            $table->dropColumn('disponible_analisis_publico');
        });
    }
};
