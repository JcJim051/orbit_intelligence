<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('micrositio_textos_inicio', function (Blueprint $table): void {
            $table->id();
            $table->string('ultimos_titulo', 180);
            $table->string('ultimos_bajada', 255)->nullable();
            $table->string('ultimos_enlace', 80)->nullable();
            $table->string('destacados_titulo', 180);
            $table->string('destacados_bajada', 255)->nullable();
            $table->string('destacados_enlace', 80)->nullable();
            $table->string('publicaciones_titulo', 180);
            $table->string('publicaciones_bajada', 255)->nullable();
            $table->string('publicaciones_enlace', 80)->nullable();
            $table->string('aliados_titulo', 180);
            $table->string('aliados_bajada', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('micrositio_textos_inicio');
    }
};
