<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dependencia_reglas_pasiva', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependencia_id')->constrained('dependencias');
            $table->string('tipo_regla', 32);
            $table->string('valor', 160);
            $table->unsignedSmallInteger('prioridad')->default(100);
            $table->unsignedSmallInteger('vigencia_desde')->nullable();
            $table->unsignedSmallInteger('vigencia_hasta')->nullable();
            $table->text('observacion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['dependencia_id', 'tipo_regla', 'valor'], 'reglas_pasiva_llave_unica');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dependencia_reglas_pasiva');
    }
};
