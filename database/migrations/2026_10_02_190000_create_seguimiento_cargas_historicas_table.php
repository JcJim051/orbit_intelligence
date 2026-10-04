<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seguimiento_cargas_historicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seguimiento_id')->constrained('seguimientos')->restrictOnDelete();
            $table->string('disk', 40);
            $table->string('path');
            $table->string('nombre_original');
            $table->char('sha256', 64);
            $table->string('proyectos_path')->nullable();
            $table->string('proyectos_nombre_original')->nullable();
            $table->char('proyectos_sha256', 64)->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 32)->default('diagnosticado');
            $table->unsignedInteger('filas_total')->default(0);
            $table->unsignedInteger('filas_validas')->default(0);
            $table->unsignedInteger('filas_bloqueadas')->default(0);
            $table->unsignedInteger('filas_importadas')->default(0);
            $table->unsignedInteger('filas_actualizadas')->default(0);
            $table->json('diagnostico')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
            $table->index(['seguimiento_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seguimiento_cargas_historicas');
    }
};
