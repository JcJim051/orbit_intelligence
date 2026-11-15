<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('micrositio_ultimos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 40);
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('capa_codigo', 40)->nullable();
            $table->string('valor', 80)->nullable();
            $table->string('unidad', 40)->nullable();
            $table->string('periodo', 40)->nullable();
            $table->string('delta_texto', 120)->nullable();
            $table->string('delta_tipo', 20)->default('neutro');
            $table->string('fuente', 255)->nullable();
            $table->string('url', 500)->nullable();
            $table->boolean('es_ejemplo')->default(false);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('micrositio_ultimos');
    }
};
