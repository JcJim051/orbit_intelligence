<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicadores_producto', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meta_producto_id')->constrained('metas_producto')->cascadeOnDelete();
            $table->string('codigo', 50)->nullable()->unique();
            $table->text('nombre');
            $table->string('unidad_medida')->nullable();
            $table->string('tipo_acumulacion', 40)->nullable();
            $table->decimal('linea_base', 18, 4)->nullable();
            $table->decimal('meta_cuatrienio', 18, 4)->nullable();
            $table->text('fuente_verificacion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('meta_producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicadores_producto');
    }
};
