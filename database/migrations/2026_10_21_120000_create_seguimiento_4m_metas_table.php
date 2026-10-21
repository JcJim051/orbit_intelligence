<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seguimiento_4m_metas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meta_producto_id')->nullable()->constrained('metas_producto')->nullOnDelete();
            $table->string('codigo_meta_producto', 32);
            $table->unsignedSmallInteger('vigencia');
            $table->string('responsable')->nullable();
            $table->string('orientacion', 32)->nullable();
            $table->decimal('programado_fisico', 20, 4)->nullable();
            $table->decimal('ejecutado_fisico', 20, 4)->nullable();
            $table->decimal('asignado', 20, 2)->nullable();
            $table->decimal('comprometido', 20, 2)->nullable();
            $table->decimal('obligado', 20, 2)->nullable();
            $table->decimal('pagado', 20, 2)->nullable();
            $table->string('estado_obligado', 24)->default('No reportado');
            $table->string('estado_pagado', 24)->default('No reportado');
            $table->text('observaciones')->nullable();
            $table->string('fuente', 80)->default('consolidado_4m');
            $table->timestamps();
            $table->unique(['codigo_meta_producto', 'vigencia']);
            $table->index(['vigencia', 'meta_producto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seguimiento_4m_metas');
    }
};
